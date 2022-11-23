<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use StefanFisk\Phpreact\Support\FooComponent;
use Throwable;

use const LIBXML_HTML_NODEFDTD;

class HtmlRendererTest extends TestCase
{
    private function assertRenderMatches(string $expected, Element $el): void
    {
        $renderer = new HtmlRenderer();

        $actual = $renderer->renderToString($el);

        $expected = $this->normalizeHtml($expected);
        $actual   = $this->normalizeHtml($actual);

        $this->assertEquals($expected, $actual);
    }

    /** @psalm-param class-string<Throwable> $exceptionName */
    private function assertRenderThrows(string $exceptionName, Element $el): void
    {
        $renderer = new HtmlRenderer();

        $this->expectException($exceptionName);

        $renderer->renderToString($el);
    }

    private function normalizeHtml(string $html): string
    {
        static $id = 'e442f1ef43914400a38c';

        $doc = new DOMDocument();

        @$doc->loadHTML('<div id="' . $id . '">' . $html . '</div>', LIBXML_HTML_NODEFDTD); // to ignore HTML5 errors

        $normalizedHtml = '';

        foreach ($doc->getElementById($id)->childNodes ?? [] as $child) {
            $normalizedHtml .= $doc->saveHTML($child);
        }

        return $normalizedHtml;
    }

    public function testInvalidTagName(): void
    {
        $this->assertRenderthrows(
            RenderError::class,
            el('"test"'),
        );
    }

    public function testInvalidAttributeName(): void
    {
        $this->assertRenderthrows(
            RenderError::class,
            el('div', ['"foo"' => 'bar']),
        );
    }

    public function testEncodesTextChildren(): void
    {
        $this->assertRenderMatches(
            '<div>Foo &gt; Bar</div>',
            el('div', [], 'Foo > Bar'),
        );
    }

    public function testDoubleEncodesTextChildren(): void
    {
        $this->assertRenderMatches(
            '<div>Foo &amp;gt; Bar</div>',
            el('div', [], 'Foo &gt; Bar'),
        );
    }

    public function testConcatenatesTextChildren(): void
    {
        $this->assertRenderMatches(
            '<div>FooBar</div>',
            el('div', [], 'Foo', 'Bar'),
        );
    }

    public function testIgnoresBoolChildren(): void
    {
        $this->assertRenderMatches(
            '<div>FooBar</div>',
            el('div', [], true, 'Foo', false, 'Bar', true),
        );
    }

    public function testPreservesChildrenWhitespace(): void
    {
        $this->assertRenderMatches(
            "<div> Foo  \t\n  Bar </div>",
            el('div', [], ' Foo ', ' ', "\t", "\n", ' ', ' Bar '),
        );
    }

    public function testIntChild(): void
    {
        $this->assertRenderMatches(
            '<div>123</div>',
            el('div', [], 123),
        );
    }

    public function testFloatChild(): void
    {
        $this->assertRenderMatches(
            '<div>123.456</div>',
            el('div', [], 123.456),
        );
    }

    public function testElementChild(): void
    {
        $this->assertRenderMatches(
            '<div><div>foo</div></div>',
            el('div', [], el('div', [], ['foo'])),
        );
    }

    public function testFlattensChildren(): void
    {
        $this->assertRenderMatches(
            '<div><div>foo</div><div>bar</div></div>',
            el('div', [], [
                [
                    [
                        el('div', [], [['foo']]),
                    ],
                ],
                el('div', [], 'bar'),
            ]),
        );
    }

    public function testEncodesTextProps(): void
    {
        $this->assertRenderMatches(
            '<div foo="&gt; bar"></div>',
            el('div', ['foo' => '> bar']),
        );
    }

    public function testDoubleEncodesTextProps(): void
    {
        $this->assertRenderMatches(
            '<div foo="&amp;gt; Bar"></div>',
            el('div', ['foo' => '&gt; Bar']),
        );
    }

    public function testEncodesTextPropQuotes(): void
    {
        $this->assertRenderMatches(
            '<div foo="&quot;Bar&quot; \'Baz\'"></div>',
            el('div', ['foo' => '"Bar" \'Baz\'']),
        );
    }

    public function testIntProps(): void
    {
        $this->assertRenderMatches(
            '<div foo="123"></div>',
            el('div', ['foo' => 123]),
        );
    }

    public function testFloatProp(): void
    {
        $this->assertRenderMatches(
            '<div foo="123.456"></div>',
            el('div', ['foo' => 123.456]),
        );
    }

    public function testIgnoredEmptyClassPropString(): void
    {
        $this->assertRenderMatches(
            '<div></div>',
            el('div', ['class' => '']),
        );
    }

    public function testSortsClassPropString(): void
    {
        $this->assertRenderMatches(
            '<div class="bar foo"></div>',
            el('div', ['class' => 'foo bar']),
        );
    }

    public function testConditionalClassProp(): void
    {
        $this->assertRenderMatches(
            '<div class="bar foo"></div>',
            el('div', ['class' => ['foo', 'bar' => true, 'baz' => false]]),
        );
    }

    public function testNestedConditionalClassProp(): void
    {
        $this->assertRenderMatches(
            '<div class="bar foo"></div>',
            el('div', ['class' => ['foo', ['bar' => true], ['baz' => false]]]),
        );
    }

    public function testSortsConditionalClassPropString(): void
    {
        $this->assertRenderMatches(
            '<div class="bar foo"></div>',
            el('div', ['class' => ['foo bar' => true]]),
        );
    }

    public function testVoidElementsDoNotHaveEndTags(): void
    {
        $this->assertRenderMatches(
            '<img foo="bar">',
            el('img', ['foo' => 'bar']),
        );
    }

    public function testVoidElementsCannotHaveChildren(): void
    {
        $this->assertRenderThrows(
            RenderError::class,
            el('img', [], 'foo'),
        );
    }

    public function testFragmentsRenderChildren(): void
    {
        $this->assertRenderMatches(
            '<div>foo</div>bar<div>baz</div>',
            el('', [], [el('div', [], 'foo'), 'bar', el('div', [], 'baz')]),
        );
    }

    public function testFragmentsCannotHaveProps(): void
    {
        $this->assertRenderThrows(
            RenderError::class,
            el('', ['foo' => 'bar'], ['baz']),
        );
    }

    public function testUnsafeHtmlRendersChild(): void
    {
        $this->assertRenderMatches(
            '<div><h1 class="unsafe">bar</h1></div>',
            el('div', [], el(':unsafe-html', [], '<h1 class="unsafe">bar</h1>')),
        );
    }

    public function testUnsafeHtmlCannotHaveProps(): void
    {
        $this->assertRenderThrows(
            RenderError::class,
            el(':unsafe-html', ['foo' => 'bar'], 'baz'),
        );
    }

    public function testUnsafeHtmlCannotHaveNonArrayChildren(): void
    {
        $this->assertRenderThrows(
            RenderError::class,
            el(':unsafe-html', ['children' => 'bar']),
        );
    }

    public function testUnsafeHtmlCannotHaveZeroChildren(): void
    {
        $this->assertRenderThrows(
            RenderError::class,
            el(':unsafe-html', []),
        );
    }

    public function testUnsafeHtmlCannotHaveMultipleChildren(): void
    {
        $this->assertRenderThrows(
            RenderError::class,
            el(':unsafe-html', [], 'foo', 'bar'),
        );
    }

    public function testRootComponent(): void
    {
        $c = static fn (array $props): Element => el(
            'div',
            ['data-foo' => $props['foo']],
            el('div', ['class' => 'children'], $props['children']),
        );

        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children">baz</div></div>',
            el($c, ['foo' => 'bar'], 'baz'),
        );
    }

    public function testChildComponent(): void
    {
        $c = static fn (array $props): Element => el(
            'div',
            ['data-foo' => $props['foo']],
            el('div', ['class' => 'children'], $props['children']),
        );

        $this->assertRenderMatches(
            '<div><div data-foo="bar"><div class="children">baz</div></div></div>',
            el('div', [], el($c, ['foo' => 'bar'], 'baz')),
        );
    }

    public function testNestedComponents(): void
    {
        $c = static fn (array $props): Element => el(
            'div',
            ['data-foo' => $props['foo']],
            el('div', ['class' => 'children'], $props['children']),
        );

        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children"><div data-foo="baz"><div class="children">qux</div></div></div></div>', // phpcs:ignore Generic.Files.LineLength.TooLong
            el($c, ['foo' => 'bar'], el($c, ['foo' => 'baz'], 'qux')),
        );
    }

    public function testClosureComponent(): void
    {
        $c = static fn (array $props): Element => el(
            'div',
            ['data-foo' => $props['foo']],
            el('div', ['class' => 'children'], $props['children']),
        );

        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children">baz</div></div>',
            el($c, ['foo' => 'bar'], 'baz'),
        );
    }

    public function testClosureComponentWithoutArgs(): void
    {
        $c = static fn (): Element => el(
            'div',
            ['data-foo' => 'bar'],
        );

        $this->assertRenderMatches(
            '<div data-foo="bar"></div>',
            el($c, [], 'baz'),
        );
    }

    public function testClassComponent(): void
    {
        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children">baz</div></div>',
            el(FooComponent::class, ['foo' => 'bar'], 'baz'),
        );
    }

    public function testClassMethodComponent(): void
    {
        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children">baz</div></div>',
            el([FooComponent::class, 'fn'], ['foo' => 'bar'], 'baz'),
        );
    }

    public function testObjectComponent(): void
    {
        $c = new class {
            /** @param array<string,mixed> $props */
            public function render(array $props): Element
            {
                return el(
                    'div',
                    ['data-foo' => $props['foo']],
                    el('div', ['class' => 'children'], $props['children']),
                );
            }
        };

        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children">baz</div></div>',
            el($c, ['foo' => 'bar'], 'baz'),
        );
    }

    public function testObjectMethodComponent(): void
    {
        $c = new class {
            /** @param array<string,mixed> $props */
            public function fn(array $props): Element
            {
                return el(
                    'div',
                    ['data-foo' => $props['foo']],
                    el('div', ['class' => 'children'], $props['children']),
                );
            }
        };

        $this->assertRenderMatches(
            '<div data-foo="bar"><div class="children">baz</div></div>',
            el([$c, 'fn'], ['foo' => 'bar'], 'baz'),
        );
    }

    public function testSingleLevelContext(): void
    {
        $c1 = static fn (array $props): Element => el(
            Context::class,
            ['foo' => 'bar'],
            $props['children'],
        );

        $c2 = static function (): Element {
            $foo = use_context('foo');

            return el('div', [], $foo);
        };

        $this->assertRenderMatches(
            '<div>bar</div>',
            el($c1, [], el($c2)),
        );
    }

    public function testMultiLevelContext(): void
    {
        $c1 = static fn (array $props): Element => el(
            Context::class,
            ['foo' => 'bar'],
            $props['children'],
        );

        $c2 = static fn (array $props): Element => el(
            Context::class,
            ['foo' => 'baz'],
            $props['children'],
        );

        $c3 = static function (): Element {
            $foo = use_context('foo');

            return el('div', [], $foo);
        };

        $this->assertRenderMatches(
            '<div>bar</div><div>baz</div><div>bar</div>',
            el($c1, [], [
                el($c3),
                el($c2, [], el($c3)),
                el($c3),
            ]),
        );
    }

    public function testModifyingExistingContext(): void
    {
        $c = static function (array $props): Element {
            $propFoo = $props['foo'] ?? null;

            $contextFoo = use_context('foo');

            return el(
                Context::class,
                ['foo' => $propFoo ?? $contextFoo],
                el('div', [], $contextFoo),
                $props['children'] ?? null,
            );
        };

        $this->assertRenderMatches(
            '<div>bar</div><div>baz</div>',
            el(
                Context::class,
                ['foo' => 'bar'],
                el($c, ['foo' => 'baz'], el($c)),
            ),
        );
    }

    public function testSetState(): void
    {
        $c = static function ($props): string {
            [$val, $setVal] = use_state('foo');
            use_effect(static fn () => $setVal('bar'), []);

            return $val;
        };

        $this->assertRenderMatches(
            'bar',
            el($c),
        );
    }

    public function testEffect(): void
    {
        $calls = 0;

        $mockFn = static function () use (&$calls): void {
            $calls += 1;
        };

        $c = static function ($props) use ($mockFn): string {
            [$val, $setVal] = use_state('foo');
            use_effect(static fn () => $setVal('bar'), []);
            use_effect($mockFn);

            return $val;
        };

        $this->assertRenderMatches(
            'bar',
            el($c),
        );

        $this->assertEquals(1, $calls);
    }

    public function testEffectCleanup(): void
    {
        $calls = 0;

        $mockFn = static function () use (&$calls): void {
            $calls += 1;
        };

        $c1 = static function ($props) use ($mockFn) {
            use_effect(static fn () => $mockFn);

            return null;
        };

        $c2 = static function ($props) use ($c1) {
            [$val, $setVal] = use_state('foo');
            use_effect(static fn () => $setVal('bar'), []);

            if ($val === 'foo') {
                return el($c1);
            }

            return 'bar';
        };

        $this->assertRenderMatches(
            'bar',
            el($c2),
        );

        $this->assertEquals(1, $calls);
    }
}
