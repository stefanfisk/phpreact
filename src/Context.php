<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

final class Context
{
    /**
     * @param array{children:mixed} $props
     *
     * @return mixed
     */
    public function render(array $props)
    {
        return $props['children'] ?? null;
    }
}
