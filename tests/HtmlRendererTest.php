<?php

declare(strict_types=1);

namespace StefanFisk\Phpreact;

use DOMDocument;
use PHPUnit\Framework\TestCase;
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
}
