<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact\Renderer;

class ContextNode extends Node
{
    /** @var array<string,mixed> */
    public array $props = [];
}
