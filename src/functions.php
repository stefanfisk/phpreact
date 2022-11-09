<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

/**
 * @param mixed $type
 * @param array<string,mixed> $props
 * @param mixed ...$children
 */
// phpcs:ignore Squiz.Functions.GlobalFunction.Found
function el($type, array $props = [], ...$children): Element
{
    return Element::create($type, $props, ...$children);
}

/** @return mixed */
// phpcs:ignore Squiz.Functions.GlobalFunction.Found
function use_context(string $key)
{
    return Context::use($key);
}
