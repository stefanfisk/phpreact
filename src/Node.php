<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

class Node
{
    public int $id;

    /** @var mixed */
    public $type;

    /** @var array<string,mixed> */
    public array $props;

    public ?Node $parent = null;

    public int $depth;

    /** @var array<Node> $children */
    public array $children = [];

    /** @var ?callable */
    public $component = null;

    /** @var array{0:string} */
    public array $hooks = [];

    /** @var array<Closure> */
    public array $pendingEffects = [];
}
