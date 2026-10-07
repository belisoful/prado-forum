<?php

use Belisoful\Forum\Content\BEForumContentRenderer;
use PHPUnit\Framework\TestCase;

class BEForumContentRendererTest extends TestCase
{
	private BEForumContentRenderer $renderer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->renderer = new BEForumContentRenderer();
		$this->renderer->setCachePath(sys_get_temp_dir() . '/beforum-purifier-test');
	}

	public function testMarkdownIsRenderedAndSanitized(): void
	{
		$html = $this->renderer->render("# Title\n\nHello **world** <script>alert(1)</script> [link](javascript:alert(1)) [ok](https://example.com)");
		self::assertStringContainsString('<h1>Title</h1>', $html);
		self::assertStringContainsString('<strong>world</strong>', $html);
		self::assertStringNotContainsString('<script', $html);
		self::assertStringNotContainsString('javascript:', $html);
		self::assertStringContainsString('href="https://example.com"', $html);
		self::assertStringContainsString('rel="nofollow', $html);
		self::assertSame('', $this->renderer->render('   '));
		self::assertSame('', $this->renderer->render(null));
	}

	public function testPlainTextFormat(): void
	{
		$html = $this->renderer->render("line one\nline two\n\n<b>bold</b>", BEForumContentRenderer::FORMAT_TEXT);
		self::assertMatchesRegularExpression('#line one<br ?/?>#', $html);
		self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
		self::assertSame(2, substr_count($html, '<p>'));
		self::assertSame([BEForumContentRenderer::FORMAT_MARKDOWN, BEForumContentRenderer::FORMAT_TEXT], $this->renderer->getFormats());
		self::assertTrue($this->renderer->isFormatSupported('MARKDOWN'));
		self::assertFalse($this->renderer->isFormatSupported('bbcode'));
	}

	public function testRawHtmlIsEscapedInMarkdown(): void
	{
		$html = $this->renderer->render('<img src=x onerror=alert(1)> <a href="https://a.b" onclick="x()">a</a>');
		self::assertStringNotContainsString('<img', $html);
		self::assertDoesNotMatchRegularExpression('/<[^>]*\bon\w+=/', $html, 'no event handler attributes survive');
		self::assertStringContainsString('&lt;img', $html, 'raw html is shown as text');
	}

	public function testMentions(): void
	{
		self::assertSame(['alice', 'bob_1'], $this->renderer->extractMentions('Hi @alice and @bob_1, not an email: me@example.com, @alice again'));
		self::assertSame([], $this->renderer->extractMentions(null));
		self::assertSame([], $this->renderer->extractMentions('no mentions'));
		$this->renderer->setMentionResolver(fn (string $name) => $name === 'alice' ? '/member/alice' : null);
		$html = $this->renderer->render("Hi @alice and @nobody\n\n`@alice in code`");
		self::assertStringContainsString('<a class="mention" href="/member/alice">@alice</a>', $html);
		self::assertStringContainsString('@nobody', $html);
		self::assertStringNotContainsString('href="/member/nobody"', $html);
		self::assertStringContainsString('<code>@alice in code</code>', $html, 'mentions inside code are untouched');
		self::assertSame(1, substr_count($html, 'href="/member/alice"'));
		self::assertSame('no at sign', $this->renderer->linkMentions('no at sign'));
		$this->renderer->setMentionResolver(null);
		self::assertSame('@alice', $this->renderer->linkMentions('@alice'));
	}

	public function testExcerptAndAllowedHtml(): void
	{
		self::assertSame('Hello world', BEForumContentRenderer::excerpt('<p>Hello   <b>world</b></p>'));
		self::assertSame('Hello…', BEForumContentRenderer::excerpt('<p>Hello world</p>', 6));
		self::assertSame('', BEForumContentRenderer::excerpt(null));
		$this->renderer->setAllowedHtml('p,strong');
		self::assertSame('p,strong', $this->renderer->getAllowedHtml());
		$html = $this->renderer->render('# Heading');
		self::assertStringNotContainsString('<h1>', $html);
		self::assertStringContainsString('Heading', $html);
	}

	public function testDynamicEventsExtendRendering(): void
	{
		$this->renderer->attachBehavior('upper', new class () extends \Prado\Util\TBehavior {
			public function dyRenderContent($html, $raw, $format, $chain)
			{
				return $chain->dyRenderContent(strtoupper($html), $raw, $format);
			}

			public function dyRenderFormat($html, $raw, $format, $chain)
			{
				if ($format === 'shout') {
					$html = '<p>' . htmlspecialchars($raw) . '!</p>';
				}
				return $chain->dyRenderFormat($html, $raw, $format);
			}
		});
		self::assertStringContainsString('<P>HELLO!</P>', $this->renderer->render('hello', 'shout'));
		self::assertTrue($this->renderer->isFormatSupported('shout'));
	}
}
