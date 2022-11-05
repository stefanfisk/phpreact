<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use InvalidArgumentException;

class Element
{
    /** @var mixed */
    private $type;

    /** @var array<string,mixed> */
    private array $props = [];

    /**
     * @param mixed               $type
     * @param array<string,mixed> $props
     * @param mixed               ...$children
     */
    public static function create($type, array $props = [], ...$children): Element
    {
        if ($children) {
            if (! empty($props['children'])) {
                throw new InvalidArgumentException('Both $props[children] and $children are non-empty.');
            }

            $props['children'] = $children;
        }

        return new Element($type, $props);
    }

    /**
     * @param mixed               $type
     * @param array<string,mixed> $props
     */
    public function __construct($type, array $props)
    {
        $this->type  = $type;
        $this->props = $props;
    }

    /** @return mixed */
    public function getType()
    {
        return $this->type;
    }

    /** @return array<string,mixed> */
    public function getProps(): array
    {
        return $this->props;
    }
}
