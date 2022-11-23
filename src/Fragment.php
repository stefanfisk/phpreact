<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

final class Fragment
{
    /**
     * @param array{children:mixed} $props
     *
     * @return mixed
     */
    public function render(array $props)
    {
        $children = $props['children'] ?? null;
        unset($props['children']);

        if ($props) {
            throw new RenderError('Fragments cannot have other props than `children`.');
        }

        return $children;
    }
}
