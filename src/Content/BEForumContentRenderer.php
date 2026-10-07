<?php

/**
 * BEForumContentRenderer class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Content;

use Belisoful\Forum\BEForumModule;
use Prado\Prado;
use Prado\TComponent;

/**
 * BEForumContentRenderer class.
 *
 * BEForumContentRenderer converts the raw content of posts, signatures and
 * descriptions into sanitised HTML.  Two formats are built in:
 *  - `markdown`: rendered by Parsedown in safe mode (raw HTML is escaped, only
 *    http/https/mailto links) with line breaks and URL auto-linking;
 *  - `text`: escaped plain text with preserved line breaks.
 *
 * Every result is then filtered by HTMLPurifier with a conservative allow list
 * so no script, style, event handler or dangerous URL survives, regardless of
 * the format.  `@username` mentions are turned into profile links when a
 * mention resolver is set.
 *
 * Extension points: `dyPreRenderContent` filters the raw text before
 * rendering, `dyRenderContent` filters the final HTML and `dyRenderFormat`
 * lets a behavior implement additional formats.
 *
 * ```php
 * $html = $forum->getRenderer()->render("**Hello** @alice", BEForumContentRenderer::FORMAT_MARKDOWN);
 * ```
 *
 * @method string dyPreRenderContent(string $raw, string $format)
 * @method string dyRenderContent(string $html, string $raw, string $format)
 * @method null|string dyRenderFormat(null|string $html, string $raw, string $format)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumContentRenderer extends TComponent
{
	public const FORMAT_MARKDOWN = 'markdown';
	public const FORMAT_TEXT = 'text';

	/** The pattern of a mention; group 1 is the username */
	public const MENTION_PATTERN = '/(?<![\w@\/])@([A-Za-z0-9][A-Za-z0-9_.\-]{1,62})/u';

	/** @var null|\WeakReference<BEForumModule> the module */
	private ?\WeakReference $_module;

	/** @var null|\HTMLPurifier the purifier instance */
	private ?\HTMLPurifier $_purifier = null;

	/** @var null|callable `function(string $username): ?string` returning the profile URL of a username, null for unknown members */
	private $_mentionResolver;

	/** @var string the allowed HTML of the purifier */
	private string $_allowedHtml = 'p,br,strong,b,em,i,u,s,del,ins,a[href|title|rel|target],ul,ol[start],li,blockquote,code,pre,h1,h2,h3,h4,h5,h6,hr,img[src|alt|title|width|height],table,thead,tbody,tfoot,tr,th[colspan|rowspan],td[colspan|rowspan],span[class],div[class],sup,sub,kbd,abbr[title],cite,q,dl,dt,dd';

	/** @var null|string the purifier cache directory */
	private ?string $_cachePath = null;

	/**
	 * @param null|BEForumModule $module the module
	 */
	public function __construct(?BEForumModule $module = null)
	{
		$this->_module = $module ? \WeakReference::create($module) : null;
		parent::__construct();
	}

	/**
	 * @return null|BEForumModule the module
	 */
	public function getModule(): ?BEForumModule
	{
		return $this->_module?->get();
	}

	/**
	 * @return string[] the supported formats
	 */
	public function getFormats(): array
	{
		return [self::FORMAT_MARKDOWN, self::FORMAT_TEXT];
	}

	/**
	 * @param string $format a format name
	 * @return bool whether the format is supported by the renderer or a behavior
	 */
	public function isFormatSupported(string $format): bool
	{
		return in_array(strtolower($format), $this->getFormats(), true) || $this->dyRenderFormat(null, '', strtolower($format)) !== null;
	}

	/**
	 * @return string the HTMLPurifier allow list
	 */
	public function getAllowedHtml(): string
	{
		return $this->_allowedHtml;
	}

	/**
	 * @param string $allowed the HTMLPurifier allow list (HTML.Allowed syntax)
	 */
	public function setAllowedHtml(string $allowed): void
	{
		$this->_allowedHtml = $allowed;
		$this->_purifier = null;
	}

	/**
	 * @return string the purifier cache directory
	 */
	public function getCachePath(): string
	{
		if ($this->_cachePath === null) {
			$app = Prado::getApplication();
			$base = $app ? $app->getRuntimePath() : sys_get_temp_dir();
			$this->_cachePath = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'beforum-purifier';
		}
		return $this->_cachePath;
	}

	/**
	 * @param string $path the purifier cache directory
	 */
	public function setCachePath(string $path): void
	{
		$this->_cachePath = $path;
		$this->_purifier = null;
	}

	/**
	 * @return null|callable the mention resolver
	 */
	public function getMentionResolver(): ?callable
	{
		return $this->_mentionResolver;
	}

	/**
	 * @param null|callable $resolver `function(string $username): ?string` returning a profile URL or null
	 */
	public function setMentionResolver(?callable $resolver): void
	{
		$this->_mentionResolver = $resolver;
	}

	/**
	 * Renders raw content to sanitised HTML.
	 * @param null|string $raw the raw content
	 * @param string $format the content format
	 * @return string the HTML
	 */
	public function render(?string $raw, string $format = self::FORMAT_MARKDOWN): string
	{
		$raw = (string) $raw;
		$format = strtolower($format);
		$raw = $this->dyPreRenderContent($raw, $format);
		if (trim($raw) === '') {
			return '';
		}
		$html = $this->dyRenderFormat(null, $raw, $format);
		if ($html === null) {
			$html = match ($format) {
				self::FORMAT_TEXT => $this->renderText($raw),
				default => $this->renderMarkdown($raw),
			};
		}
		$html = $this->purify($html);
		$html = $this->linkMentions($html);
		return $this->dyRenderContent($html, $raw, $format);
	}

	/**
	 * Renders Markdown with Parsedown in safe mode.
	 * @param string $raw the Markdown text
	 * @return string the HTML (not yet purified)
	 */
	public function renderMarkdown(string $raw): string
	{
		$parser = new \Parsedown();
		$parser->setSafeMode(true);
		$parser->setBreaksEnabled(true);
		$parser->setUrlsLinked(true);
		return $parser->text($raw);
	}

	/**
	 * Renders plain text: escaped, paragraphs and line breaks preserved.
	 * @param string $raw the text
	 * @return string the HTML (not yet purified)
	 */
	public function renderText(string $raw): string
	{
		$paragraphs = preg_split('/\R{2,}/', trim($raw)) ?: [];
		$html = '';
		foreach ($paragraphs as $paragraph) {
			$html .= '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false) . '</p>';
		}
		return $html;
	}

	/**
	 * @return \HTMLPurifier_Config the purifier configuration
	 */
	public function createPurifierConfig(): \HTMLPurifier_Config
	{
		$config = \HTMLPurifier_Config::createDefault();
		$config->set('Core.Encoding', 'UTF-8');
		$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
		$config->set('HTML.Allowed', $this->_allowedHtml);
		$config->set('HTML.Nofollow', true);
		$config->set('HTML.TargetBlank', true);
		$config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
		$config->set('AutoFormat.RemoveEmpty', true);
		$config->set('Attr.AllowedFrameTargets', ['_blank']);
		$config->set('Attr.AllowedClasses', ['language-php', 'language-js', 'language-javascript', 'language-html', 'language-css', 'language-sql', 'language-json', 'language-bash', 'language-xml', 'language-text', 'spoiler', 'mention']);
		$path = $this->getCachePath();
		if (!is_dir($path)) {
			@mkdir($path, 0o775, true);
		}
		if (is_dir($path) && is_writable($path)) {
			$config->set('Cache.SerializerPath', $path);
		} else {
			$config->set('Cache.DefinitionImpl', null);
		}
		return $config;
	}

	/**
	 * @return \HTMLPurifier the shared purifier
	 */
	public function getPurifier(): \HTMLPurifier
	{
		if ($this->_purifier === null) {
			$this->_purifier = new \HTMLPurifier($this->createPurifierConfig());
		}
		return $this->_purifier;
	}

	/**
	 * Removes every unsafe construct from HTML.
	 * @param string $html the HTML
	 * @return string the purified HTML
	 */
	public function purify(string $html): string
	{
		return $this->getPurifier()->purify($html);
	}

	/**
	 * Finds the usernames mentioned as `@username` in raw content.
	 * @param null|string $raw the raw content
	 * @return string[] the unique usernames in order of appearance
	 */
	public function extractMentions(?string $raw): array
	{
		if ($raw === null || $raw === '' || !preg_match_all(self::MENTION_PATTERN, $raw, $matches)) {
			return [];
		}
		$names = [];
		foreach ($matches[1] as $name) {
			$key = strtolower($name);
			if (!isset($names[$key])) {
				$names[$key] = $name;
			}
		}
		return array_values($names);
	}

	/**
	 * Wraps `@username` mentions found in text nodes with profile links.  Text
	 * inside `a`, `code` and `pre` elements is left alone.
	 * @param string $html purified HTML
	 * @return string the HTML with mention links
	 */
	public function linkMentions(string $html): string
	{
		$resolver = $this->_mentionResolver;
		if ($resolver === null || !str_contains($html, '@')) {
			return $html;
		}
		$parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
		$skip = 0;
		$result = '';
		foreach ($parts as $part) {
			if ($part !== '' && $part[0] === '<') {
				if (preg_match('/^<\s*(a|code|pre)\b/i', $part)) {
					$skip++;
				} elseif (preg_match('#^<\s*/\s*(a|code|pre)\b#i', $part)) {
					$skip = max(0, $skip - 1);
				}
				$result .= $part;
				continue;
			}
			if ($skip > 0 || !str_contains($part, '@')) {
				$result .= $part;
				continue;
			}
			$result .= preg_replace_callback(self::MENTION_PATTERN, function (array $match) use ($resolver): string {
				$url = $resolver($match[1]);
				if (!is_string($url) || $url === '') {
					return $match[0];
				}
				return '<a class="mention" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">@' . htmlspecialchars($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</a>';
			}, $part) ?? $part;
		}
		return $result;
	}

	/**
	 * Produces a plain text excerpt of HTML.
	 * @param null|string $html the HTML
	 * @param int $length the maximum length in characters
	 * @return string the excerpt
	 */
	public static function excerpt(?string $html, int $length = 200): string
	{
		$text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
		if (mb_strlen($text) <= $length) {
			return $text;
		}
		return rtrim(mb_substr($text, 0, max(1, $length - 1))) . '…';
	}
}
