<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use Closure;
use StefanFisk\Phpreact\Renderer\ComponentNode;
use StefanFisk\Phpreact\Renderer\ContextNode;
use StefanFisk\Phpreact\Renderer\FragmentNode;
use StefanFisk\Phpreact\Renderer\Node;
use StefanFisk\Phpreact\Renderer\ScalarNode;
use StefanFisk\Phpreact\Renderer\TagNode;

use function array_filter;
use function array_map;
use function array_walk_recursive;
use function call_user_func;
use function class_exists;
use function count;
use function function_exists;
use function get_class;
use function gettype;
use function is_array;
use function is_bool;
use function is_callable;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function sprintf;

class NodeRenderer
{
    private static ?NodeRenderer $instance = null;

    public static function getInstance(): ?NodeRenderer
    {
        return self::$instance;
    }

    public static function setInstance(?NodeRenderer $instance): void
    {
        self::$instance = $instance;
    }

    private ?Node $currentNode;

    /** @param mixed $el */
    public function render($el): ?Node
    {
        $oldInstance = self::getInstance();
        self::setInstance($this);

        $node = $this->renderFrom($el, null);

        self::setInstance($oldInstance);

        return $node;
    }

    /** @param mixed $el */
    private function renderFrom($el, ?Node $parent): ?Node
    {
        $node = $this->nodeFrom($el, $parent);

        if (! $node) {
            return null;
        }

        $this->currentNode = $node;

        $elementChildren = null;

        if ($node instanceof TagNode) {
            $elementChildren = $node->props['children'] ?? [];
        } elseif ($node instanceof FragmentNode) {
            $elementChildren = $node->props['children'] ?? [];
        } elseif ($node instanceof ContextNode) {
            foreach ($node->props as $name => $value) {
                if ($name === 'children') {
                    continue;
                }

                $this->context[$name] = $value;
            }

            $elementChildren = $node->props['children'] ?? [];
        } elseif ($node instanceof ComponentNode) {
            $elementChildren = call_user_func($node->type, $node->props);
        }

        if ($elementChildren) {
            $node->children = array_filter(array_map(
                fn ($child) => $this->renderFrom($child, $node),
                $this->toChildArray($elementChildren),
            ));
        }

        $this->currentNode = $parent;

        return $node;
    }

    /** @param mixed $el */
    private function nodeFrom($el, ?Node $parent): ?Node
    {
        // Void

        if ($el === null || is_bool($el) || $el === '') {
            return null;
        }

        // Scalars

        if (is_scalar($el)) {
            $node = new ScalarNode();

            $node->parent = $parent;
            $node->value  = $el;

            return $node;
        }

        // Arrays

        if (is_array($el)) {
            $node = new FragmentNode();

            $node->parent = $parent;
            $node->props  = ['children' => $this->toChildArray($el)];

            return $node;
        }

        // Elements

        if (! $el instanceof Element) {
            $type = is_scalar($el) ? gettype($el) : get_class($el);

            throw new RenderError(sprintf('Unsupported type %s.', $type));
        }

        $type  = $el->getType();
        $props = $el->getProps();

        // Fragment

        if ($type === '') {
            $children = $props['children'] ?? null ?: [];
            unset($props['children']);

            if ($props) {
                throw new RenderError('Fragments cannot have other props than children.');
            }

            $node = new FragmentNode();

            $node->parent = $parent;
            $node->props  = ['children' => $this->toChildArray($children)];

            return $node;
        }

        // Context

        if ($type === Context::class) {
            $node = new ContextNode();

            $node->parent = $parent;
            $node->props  = $props;

            return $node;
        }

        // Closure component

        if ($type instanceof Closure) {
            $node = new ComponentNode();

            $node->parent = $parent;
            $node->type   = $type;
            $node->props  = $props;

            return $node;
        }

        // Function component

        if (is_string($type) && function_exists($type)) {
            $node = new ComponentNode();

            $node->parent = $parent;
            $node->type   = $type;
            $node->props  = $props;

            return $node;
        }

        // Object component with default method

        if (is_object($type) && method_exists($type, 'render')) {
            $node = new ComponentNode();

            $node->parent = $parent;
            $node->type   = [$type, 'render'];
            $node->props  = $props;

            return $node;
        }

        // Object component with custom method

        if (
            is_array($type)
            && count($type) === 2
            && is_object($type[0])
            && is_string($type[1])
            && is_callable([$type[0], $type[1]])
        ) {
            $node = new ComponentNode();

            $node->parent = $parent;
            $node->type   = $type;
            $node->props  = $props;

            return $node;
        }

        // Class component with default method

        if (is_string($type) && class_exists($type) && method_exists($type, 'render')) {
            $node = new ComponentNode();

            $node->parent = $parent;
            $node->type   = [new $type(), 'render'];
            $node->props  = $props;

            return $node;
        }

        // Class component with custom method

        if (
            is_array($type)
            && count($type) === 2
            && is_string($type[0])
            && is_string($type[1])
            && class_exists($type[0])
            && method_exists($type[0], $type[1])
        ) {
            $component = new $type[0]();

            $node = new ComponentNode();

            $node->parent = $parent;
            $node->type   = [new $type[0](), $type[1]];
            $node->props  = $props;

            return $node;
        }

        // HTML tag

        if (is_string($type)) {
            $node = new TagNode();

            $node->parent = $parent;
            $node->name   = $type;
            $node->props  = $props;

            return $node;
        }

        // Unsupported type

        throw new RenderError(sprintf('Unsupported element type %s.', gettype($type)));
    }

    /**
     * @param mixed $children
     *
     * @return array<mixed>
     */
    private function toChildArray($children): array
    {
        if (! is_array($children)) {
            return [$children];
        }

        $flatChildren = [];

        array_walk_recursive($children, static function ($a) use (&$flatChildren): void {
            $flatChildren[] = $a;
        });

        return $flatChildren;
    }

    /** @return mixed */
    private function getFromContext(string $key, ?Node $node)
    {
        if (! $node) {
            throw new RenderError(sprintf('Context `%s` has not been provided.', $key));
        }

        if ($node instanceof ContextNode && isset($node->props[$key])) {
            return $node->props[$key];
        }

        return $this->getFromContext($key, $node->parent);
    }

    /** @return mixed */
    public function useContext(string $key)
    {
        return $this->getFromContext($key, $this->currentNode);
    }
}
