<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact\Renderer;

class TagNode extends Node
{
    public string $name;

    /** @var array<string,scalar> */
    public array $props;
}
