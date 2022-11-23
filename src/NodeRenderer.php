<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use Closure;

use function array_filter;
use function array_shift;
use function array_splice;
use function array_walk_recursive;
use function call_user_func;
use function class_exists;
use function count;
use function function_exists;
use function gettype;
use function in_array;
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

    private int $nextNodeId = 1;

    /** @var array<Node> */
    private array $rerenderQueue = [];

    private ?Node $currentNode;
    private bool $isInitialRender;
    private int $currentHookI;

    /** @param mixed $el */
    public function render($el): ?Node
    {
        $oldInstance = self::getInstance();
        self::setInstance($this);

        $node = $this->renderFrom($el, null, null);

        $this->processRerenderQueue();

        self::setInstance($oldInstance);

        return $node;
    }

    private function enqueueRerender(Node $node): void
    {
        if (in_array($node, $this->rerenderQueue)) {
            return;
        }

        $this->rerenderQueue[] = $node;
    }

    private function processRerenderQueue(): void
    {
        while ($this->rerenderQueue) {
            $i = 0;
            $a = $this->rerenderQueue[$i];

            for ($j = 0; $j < count($this->rerenderQueue); $j++) {
                $b = $this->rerenderQueue[$j];

                if ($a->depth < $b->depth) {
                    continue;
                }

                $i = $j;
                $b = $a;
            }

            array_splice($this->rerenderQueue, $i, 1);

            //TODO: Fix this super ugly hack
            $el = Element::create($a->type, $a->props);

            $this->renderFrom($el, $a->parent, $a);
        }
    }

    /** @param mixed $el */
    private function renderFrom($el, ?Node $parent, ?Node $oldNode): ?Node
    {
        $node = $this->nodeFrom($el, $parent);

        // If there's nothing to render, just unmount the old node

        if (! $node) {
            if ($oldNode) {
                $this->unmountNode($oldNode);
            }

            return null;
        }

        // If we have an old node

        if ($oldNode) {
            // If the node type changed
            if ($node->type !== $oldNode->type) {
                // Unmount the old and continue with new
                $this->unmountNode($oldNode);

                $oldNode = null;
            } else {
                // Reuse the old node with the new props
                $oldNode->props = $node->props;

                $node = $oldNode;
            }
        }

        // Render children

        $this->currentNode = $node;

        if ($node->component) {
            do {
                $this->isInitialRender = ! $oldNode;
                $this->currentHookI    = 0;

                $childEls = call_user_func($node->component, $node->props);

                while ($node->pendingEffects) {
                    array_shift($node->pendingEffects)();
                }

                if ($this->currentHookI !== count($node->hooks)) {
                    throw new RenderError('Hooks must be called in exact same order on every render.');
                }
            } while ($node->pendingEffects);
        } else {
            $childEls = $node->props['children'] ?? [];
        }

        $childEls = $this->toChildArray($childEls);

        $newChildren = [];

        foreach ($childEls as $i => $childEl) {
            $newChildren[] = $this->renderFrom($childEl, $node, $node->children[$i] ?? null);
        }

        for ($i = count($newChildren); $i < count($node->children); $i++) {
            $this->unmountNode($node->children[$i]);
        }

        $node->children = $newChildren;

        $this->currentNode = $parent;

        return $node;
    }

    /** @param mixed $el */
    private function nodeFrom($el, ?Node $parent): ?Node
    {
        $type      = null;
        $props     = [];
        $component = null;

        // Void

        if ($el === null || is_bool($el) || $el === '') {
            return null;
        }

        // Scalars

        if (is_scalar($el)) {
            $type           = Scalar::class;
            $props['value'] = $el;
        }

        // Fragments

        if (is_array($el)) {
            $type              = Fragment::class;
            $props['children'] = $el;
        }

        // Elements

        if ($el instanceof Element) {
            $type  = $el->getType();
            $props = $el->getProps();

            if ($type instanceof Closure) {
                // Closure component

                $component = $type;
            } elseif (is_string($type) && function_exists($type)) {
                // Function component

                $component = $type;
            } elseif (is_object($type) && method_exists($type, 'render')) {
                // Object component with default method

                $component = [$type, 'render'];
            } elseif (
                is_array($type)
                && count($type) === 2
                && is_object($type[0])
                && is_string($type[1])
                && is_callable([$type[0], $type[1]])
            ) {
                // Object component with custom method

                $component = $type;
            } elseif (is_string($type) && class_exists($type) && method_exists($type, 'render')) {
                // Class component with default method
                $component = [new $type(), 'render'];
            } elseif (
                is_array($type)
                && count($type) === 2
                && is_string($type[0])
                && is_string($type[1])
                && class_exists($type[0])
                && method_exists($type[0], $type[1])
            ) {
                $component = [new $type[0](), $type[1]];
            }
        }

        // Error?

        if (! $type) {
            throw new RenderError(sprintf('Unsupported element type `%s`.', gettype($el)));
        }

        // Done

        $node = new Node();

        $node->id = $this->nextNodeId++;

        $node->type  = $type;
        $node->props = $props;

        $node->component = $component;

        $node->parent = $parent;
        $node->depth  = $parent ? $parent->depth + 1 : 0;

        return $node;
    }

    /**
     * @param mixed $children
     *
     * @return array<mixed>
     */
    private function toChildArray($children): array
    {
        if (! is_array($children)) {
            $children = [$children];
        }

        $flatChildren = [];

        array_walk_recursive($children, static function ($el) use (&$flatChildren): void {
            if ($el === null || is_bool($el) || $el === '') {
                return;
            }

            $flatChildren[] = $el;
        });

        return $flatChildren;
    }

    private function unmountNode(Node $node): void
    {
        foreach ($node->children as $child) {
            $this->unmountNode($child);
        }

        foreach ($node->hooks as $hook) {
            if ($hook[0] !== 'effect') {
                continue;
            }

            $cleanupFn = $hook[3] ?? null;

            if (! $cleanupFn) {
                continue;
            }

            call_user_func($cleanupFn);
        }

         $this->rerenderQueue = array_filter($this->rerenderQueue, static fn ($n) => $n === $node);
    }

    /** @return mixed */
    private function getFromContext(string $key, ?Node $node)
    {
        if (! $node) {
            throw new RenderError(sprintf('Context `%s` has not been provided.', $key));
        }

        if ($node->type === Context::class && isset($node->props[$key])) {
            return $node->props[$key];
        }

        return $this->getFromContext($key, $node->parent);
    }

    /** @return mixed */
    public function useContext(string $key)
    {
        $node = $this->currentNode;

        if (! $node->component) {
            throw new RenderError('Cannot call hooks outside of component render.');
        }

        $currentHookI = $this->currentHookI;

        if ($this->isInitialRender) {
            $node->hooks[] = ['context', $key];
        } else {
            $hook = $node->hooks[$currentHookI];

            if ($hook[0] !== 'context' || $hook[1] !== $key) {
                throw new RenderError('Hooks must be called in exact same order on every render.');
            }
        }

        $this->currentHookI += 1;

        return $this->getFromContext($key, $this->currentNode);
    }

    public function useEffect(callable $fn, ?array $deps = null): void
    {
        $node = $this->currentNode;

        if (! $node->component) {
            throw new RenderError('Cannot call hooks outside of component render.');
        }

        $currentHookI = $this->currentHookI;

        $enqueueEffect = false;

        if ($this->isInitialRender) {
            $hook = ['effect', $fn, $deps, null];

            $enqueueEffect = true;

            $node->hooks[] = $hook;
        } else {
            $hook = $node->hooks[$currentHookI];

            if ($hook[0] !== 'effect') {
                throw new RenderError('Hooks must be called in exact same order on every render.');
            }

            $oldDeps = $hook[2];

            if ($deps === null || $oldDeps !== $deps) {
                $enqueueEffect = true;
            }
        }

        if ($enqueueEffect) {
            $node->pendingEffects[] = static function () use ($node, $currentHookI, $fn): void {
                $cleanupFn = call_user_func($fn);

                $node->hooks[$currentHookI][3] = $cleanupFn;
            };

            $node->hooks[$currentHookI][2] = $deps;
        }

        $this->currentHookI += 1;
    }

    /** @return mixed */
    public function useMemo(callable $fn, ?array $deps = null)
    {
        $node = $this->currentNode;

        if (! $node->component) {
            throw new RenderError('Cannot call hooks outside of component render.');
        }

        $currentHookI = $this->currentHookI;

        $updateValue = false;

        if ($this->isInitialRender) {
            $hook = ['memo', $fn, $deps, null];

            $updateValue = true;

            $node->hooks[] = $hook;
        } else {
            $hook = $node->hooks[$currentHookI];

            if ($hook[0] !== 'memo') {
                throw new RenderError('Hooks must be called in exact same order on every render.');
            }

            $oldDeps = $hook[2];

            if ($deps === null || $oldDeps !== $deps) {
                $updateValue = true;
            }
        }

        if ($updateValue) {
            $node->hooks[$currentHookI][3] = call_user_func($fn);
        }

        $this->currentHookI += 1;

        return $node->hooks[$currentHookI][3];
    }

    /**
     * @param mixed $initialValue
     *
     * @return array{0:mixed,1:Closure(mixed):void}
     */
    public function useState($initialValue): array
    {
        $node = $this->currentNode;

        if (! $node->component) {
            throw new RenderError('Cannot call hooks outside of component render.');
        }

        $currentHookI = $this->currentHookI;

        if ($this->isInitialRender) {
            $setter = function ($newValue) use ($node, $currentHookI): void {
                $node->hooks[$currentHookI][1] = $newValue;

                $this->enqueueRerender($node);
            };

            $hook = ['state', $initialValue, $setter];

            $node->hooks[] = $hook;
        } else {
            $hook = $node->hooks[$currentHookI];

            if ($hook[0] !== 'state') {
                throw new RenderError('Hooks must be called in exact same order on every render.');
            }
        }

        $this->currentHookI += 1;

        return [$hook[1], $hook[2]];
    }
}
