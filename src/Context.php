<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use RuntimeException;

final class Context
{
    /** @var callable(string):mixed|null */
    private static $resolver = null;

    /** @return mixed */
    public static function use(string $key)
    {
        if (! static::$resolver) {
            throw new RuntimeException('No resolver has been set.');
        }

        return (static::$resolver)($key);
    }

    /** @return callable(string):mixed|null*/
    public static function getResolver(): ?callable
    {
        return static::$resolver;
    }

    /** @param callable(string):mixed|null $resolver */
    public static function setResolver(?callable $resolver): void
    {
        static::$resolver = $resolver;
    }
}
