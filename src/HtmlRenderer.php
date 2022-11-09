<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use Closure;
use InvalidArgumentException;
use Throwable;

use function array_map;
use function call_user_func;
use function class_exists;
use function count;
use function function_exists;
use function gettype;
use function htmlspecialchars;
use function is_array;
use function is_bool;
use function is_callable;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
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

    /** @var array<string,mixed> */
    private array $context = [];

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

        // Fragment

        if ($type === '') {
            $this->renderFragment($props);

            return;
        }

        // Unsafe HTML

        if ($type === ':unsafe-html') {
            $this->renderUnsafeHtml($props);

            return;
        }

        // Context

        if ($type === Context::class) {
            $this->renderContext($props);

            return;
        }

        // Closure component

        if ($type instanceof Closure) {
            $this->renderComponent($type, $props);

            return;
        }

        // Function component

        if (is_string($type) && function_exists($type)) {
            $this->renderComponent($type, $props);

            return;
        }

        // Object component with default method

        if (is_object($type) && method_exists($type, 'render')) {
            $this->renderComponent([$type, 'render'], $props);

            return;
        }

        // Object component with custom method

        if (
            is_array($type)
            && count($type) === 2
            && is_object($type[0])
            && is_string($type[1])
            && method_exists($type[0], $type[1])
        ) {
            $this->renderComponent($type, $props);

            return;
        }

        // Class component with default method

        if (is_string($type) && class_exists($type) && method_exists($type, 'render')) {
            $component = new $type();

            $this->renderComponent([$component, 'render'], $props);

            return;
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

            $this->renderComponent([$component, $type[1]], $props);

            return;
        }

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
    private function renderContext(array $props): void
    {
        $children = $props['children'] ?? null ?: [];
        unset($props['children']);

        foreach ($props as $name => $value) {
            $this->context[$name] = $value;
        }

        $this->render($children);
    }

    /** @param array<string,mixed> $props */
    private function renderFragment(array $props): void
    {
        $children = $props['children'] ?? null ?: [];
        unset($props['children']);

        if ($props) {
            throw new RenderError('Fragments cannot have other props than children.');
        }

        if (! is_array($children)) {
            throw new RenderError(sprintf('Unsupported $props[children] type %s.', gettype($children)));
        }

        $this->renderArray($children);
    }

    /** @param array<string,mixed> $props */
    private function renderUnsafeHtml(array $props): void
    {
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

    /** @param array<string,mixed> $props */
    private function renderComponent(callable $type, array $props): void
    {
        $oldResolver = Context::getResolver();

        $parentContext = $this->context;

        Context::setResolver(fn ($key) => $this->getFromContext($key));

        $this->render(call_user_func($type, $props));

        Context::setResolver($oldResolver);

        $this->context = $parentContext;
    }

    /** @return mixed */
    private function getFromContext(string $key)
    {
        if (! isset($this->context[$key])) {
            throw new RenderError(sprintf('Context `%s` has not been provided.', $key));
        }

        return $this->context[$key];
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
