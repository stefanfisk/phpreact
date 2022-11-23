<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

/**
 * @param mixed               $type
 * @param array<string,mixed> $props
 * @param mixed               ...$children
 */
function el($type, array $props = [], ...$children): Element
{
    return Element::create($type, $props, ...$children);
}

/** @return mixed */
function use_context(string $key)
{
    return NodeRenderer::getInstance()->useContext($key);
}

/** @return mixed */
function use_memo(callable $fn, ?array $deps = null)
{
    return NodeRenderer::getInstance()->useMemo($fn, $deps);
}

function use_effect(callable $fn, ?array $deps = null): void
{
    NodeRenderer::getInstance()->useEffect($fn, $deps);
}

/**
 * @param mixed $initialValue
 *
 * @return array{0:mixed,1:Closure(mixed):void}
 */
function use_state($initialValue): array
{
    return NodeRenderer::getInstance()->useState($initialValue);
}

/** @param mixed $el */
function render($el): void
{
    $renderer = new HtmlRenderer();

    $renderer->render($el);
}

/** @param mixed $el */
function render_to_string($el): string
{
    $renderer = new HtmlRenderer();

    return $renderer->renderToString($el);
}
