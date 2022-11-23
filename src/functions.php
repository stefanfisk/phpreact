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
