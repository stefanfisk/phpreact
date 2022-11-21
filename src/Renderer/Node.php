<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact\Renderer;

abstract class Node
{
    public ?Node $parent = null;

    /** @var array<Node> $children */
    public array $children = [];
}
