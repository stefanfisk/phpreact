<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ElementTest extends TestCase
{
    public function testCreateMergesChildrenIntoProps(): void
    {
        $this->assertEquals(
            new Element('div', ['foo' => 'bar', 'children' => [['baz', 'qux'], 'quux']]),
            Element::create('div', ['foo' => 'bar'], ['baz', 'qux'], 'quux'),
        );
    }

    public function testCreateDoesNotMergeEmptyChildrenIntoProps(): void
    {
        $this->assertEquals(
            new Element('div', ['foo' => 'bar']),
            Element::create('div', ['foo' => 'bar']),
        );
    }

    public function testCreatePassesChildrenPropAsIs(): void
    {
        $this->assertEquals(
            new Element('div', ['foo' => 'bar', 'children' => 'baz']),
            Element::create('div', ['foo' => 'bar', 'children' => 'baz']),
        );
    }

    public function testThrowsIfBothChildrenPropAndChildren(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Element::create('div', ['foo' => 'bar', 'children' => 'baz'], ['quz']);
    }
}
