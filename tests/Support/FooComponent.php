<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact\Support;

use StefanFisk\Phpreact\Element;

use function StefanFisk\Phpreact\el;

class FooComponent
{
    /** @param array<string,mixed> $props */
    public function render(array $props): Element
    {
        return el(
            'div',
            ['data-foo' => $props['foo']],
            el('div', ['class' => 'children'], $props['children']),
        );
    }

    /** @param array<string,mixed> $props */
    public function fn(array $props): Element
    {
        return $this->render($props);
    }
}
