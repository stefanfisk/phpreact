<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

final class Scalar
{
    /**
     * @param array{value:scalar} $props
     *
     * @return mixed
     */
    public function render(array $props)
    {
        return $props['value'] ?? null;
    }
}
