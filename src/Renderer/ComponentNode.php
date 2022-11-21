<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact\Renderer;

class ComponentNode extends Node
{
    /** @var callable */
    public $type;

    /** @var array<string,mixed> */
    public array $props;
}
