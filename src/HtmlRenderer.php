<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use InvalidArgumentException;
use StefanFisk\Phpreact\Renderer\Node;
use StefanFisk\Phpreact\Renderer\ScalarNode;
use StefanFisk\Phpreact\Renderer\TagNode;
use Throwable;

use function array_filter;
use function array_map;
use function array_push;
use function array_walk_recursive;
use function count;
use function explode;
use function gettype;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function preg_match;
use function sort;
use function sprintf;

use const ENT_HTML5;
use const ENT_QUOTES;

class HtmlRenderer
{
    private const VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    /** @param mixed $el */
    public function renderToString($el): string
    {
        $obLevel = ob_get_level();

        ob_start();

        try {
            $this->render($el);
        } catch (Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }

            throw $e;
        }

        return ob_get_clean();
    }

    /** @param mixed $el */
    public function render($el): void
    {
        $nodeRenderer = new NodeRenderer();

        $node = $nodeRenderer->render($el);

        $this->renderNode($node);
    }

    private function renderNode(?Node $node): void
    {
        // Void

        if (! $node) {
            return;
        }

        // Scalars

        if ($node instanceof ScalarNode) {
            $this->renderScalar($node);

            return;
        }

        // Tags

        if ($node instanceof TagNode) {
            if ($node->name === ':unsafe-html') {
                $this->renderUnsafeHtml($node);
            } else {
                $this->renderTag($node);
            }

            return;
        }

        // Everything else

        $this->renderArray($node->children);
    }

    private function renderScalar(ScalarNode $node): void
    {
        echo $this->escape($node->value);
    }

    /** @param array<Node> $nodes */
    private function renderArray(array $nodes): void
    {
        array_map(fn (Node $node) => $this->renderNode($node), $nodes);
    }

    private function renderUnsafeHtml(TagNode $node): void
    {
        $props = $node->props;

        $children = $props['children'] ?? null ?: [];
        unset($props['children']);

        if ($props) {
            throw new RenderError('<:unsafe-html> cannot have other props than children.');
        }

        if (! is_array($children)) {
            throw new RenderError(sprintf('Unsupported $props[children] type %s.', gettype($children)));
        }

        if (count($children) !== 1) {
            throw new RenderError(sprintf('<:unsafe-html> must have exactly 1 child.', gettype($children)));
        }

        $unsafeHtml = $children[0];

        echo $unsafeHtml;
    }

    private function renderTag(TagNode $node): void
    {
        $name  = $node->name;
        $props = $node->props;

        if ($name === '') {
            throw new InvalidArgumentException('HTML tag cannot be empty string.');
        }

        if ($this->isUnsafeName($name)) {
            throw new RenderError(sprintf('%s is not a valid HTML tag name.', $name));
        }

        $isVoid = self::VOID_ELEMENTS[$name] ?? false;

        $children = $props['children'] ?? null ?: [];
        unset($props['children']);

        if ($children && ! is_array($children)) {
            throw new RenderError(sprintf('Unsupported $props[children] type %s.', gettype($children)));
        }

        if ($isVoid && $children) {
            throw new RenderError(sprintf('<%s> is a void element, and cannot have children.', $name));
        }

        echo '<' . $name;

        foreach ($props as $attName => $attValue) {
            if ($this->isUnsafeName($attName)) {
                throw new RenderError(sprintf('`%s` is not a valid attribute name.', $name));
            }

            if ($attValue === null || $attValue === false) {
                continue;
            }

            if ($attName === 'class') {
                $attValue = $this->classnames($attValue);

                if (! $attValue) {
                    continue;
                }
            }

            echo ' ';
            echo $this->escape($attName);

            if ($attValue === true) {
                continue;
            }

            echo '="';
            echo $this->escape($attValue);
            echo '"';
        }

        echo '>';

        foreach ($children as $child) {
            $this->render($child);
        }

        if ($isVoid) {
            return;
        }

        echo '</' . $name . '>';
    }

    private function isUnsafeName(string $name): bool
    {
        return preg_match('/[\s\n\\/=\'"\0<>]/', $name) === 1;
    }

    /** @param mixed $value */
    private function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }

    /** @param mixed $classes */
    private function classnames($classes): string
    {
        if (! $classes) {
            return '';
        }

        if (is_string($classes)) {
            $classes = array_filter(explode(' ', $classes));
        }

        if (! is_array($classes)) {
            throw new RenderError(sprintf('Unsupported $props[class] type %s.', gettype($classes)));
        }

        $effectiveClasses = [];

        array_walk_recursive(
            $classes,
            /**
             * @param mixed $value
             * @param int|string $key
             */
            static function ($value, $key) use (&$effectiveClasses): void {
                if (is_int($key)) {
                    $class = $value;
                } elseif ($value) {
                    $class = $key;
                } else {
                    return;
                }

                array_push($effectiveClasses, ...explode(' ', $class));
            },
        );

        $effectiveClasses = array_map('trim', $effectiveClasses);
        $effectiveClasses = array_filter($effectiveClasses);

        sort($effectiveClasses);

        return implode(' ', $effectiveClasses);
    }
}
