<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use InvalidArgumentException;
use Throwable;

use function array_map;
use function gettype;
use function htmlspecialchars;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_string;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function preg_match;
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
        // Void

        if ($el === null || is_bool($el) || $el === '') {
            return;
        }

        // Scalars

        if (is_scalar($el)) {
            $this->renderScalar($el);

            return;
        }

        // Arrays

        if (is_array($el)) {
            $this->renderArray($el);

            return;
        }

        // Elements

        if (! $el instanceof Element) {
            throw new RenderError(sprintf('Unsupported type %s.', gettype($el)));
        }

        $type  = $el->getType();
        $props = $el->getProps();

        // HTML tag

        if (is_string($type)) {
            $this->renderTag($type, $props);

            return;
        }

        // Unsupported type

        throw new RenderError(sprintf('Unsupported element type %s.', gettype($type)));
    }

    /** @param scalar $value */
    private function renderScalar($value): void
    {
        echo $this->escape($value);
    }

    /** @param array<mixed> $els */
    private function renderArray(array $els): void
    {
        array_map([$this, 'render'], $els);
    }

    /** @param array<string,mixed> $props */
    private function renderTag(string $type, array $props): void
    {
        if ($type === '') {
            throw new InvalidArgumentException('HTML tag cannot be empty string.');
        }

        if ($this->isUnsafeName($type)) {
            throw new RenderError(sprintf('%s is not a valid HTML tag name.', $type));
        }

        $isVoid = self::VOID_ELEMENTS[$type] ?? false;

        $children = $props['children'] ?? null ?: [];
        unset($props['children']);

        if ($children && ! is_array($children)) {
            throw new RenderError(sprintf('Unsupported $props[children] type %s.', gettype($children)));
        }

        if ($isVoid && $children) {
            throw new RenderError(sprintf('<%s> is a void element, and cannot have children.', $type));
        }

        echo '<' . $type;

        foreach ($props as $name => $value) {
            if ($this->isUnsafeName($name)) {
                throw new RenderError(sprintf('`%s` is not a valid attribute name.', $name));
            }

            if ($value === null || $value === false) {
                continue;
            }

            echo ' ';
            echo $this->escape($name);

            if ($value === true) {
                continue;
            }

            echo '="';
            echo $this->escape($value);
            echo '"';
        }

        echo '>';

        foreach ($children as $child) {
            $this->render($child);
        }

        if ($isVoid) {
            return;
        }

        echo '</' . $type . '>';
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
}
