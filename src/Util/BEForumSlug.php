<?php

/**
 * BEForumSlug class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Util;

/**
 * BEForumSlug class.
 *
 * BEForumSlug turns titles and names into URL friendly slugs: lowercase ASCII
 * letters, digits and single hyphens.  Non-ASCII text is transliterated when
 * the intl or iconv extensions are available and stripped otherwise.  An empty
 * result falls back to `n-a`.
 *
 * ```php
 * BEForumSlug::create('Héllo, World!');            // hello-world
 * BEForumSlug::unique('hello-world', fn($s) => $s === 'hello-world'); // hello-world-2
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
final class BEForumSlug
{
	/** The slug used when nothing remains after normalisation */
	public const EMPTY_SLUG = 'n-a';

	/**
	 * Creates a slug from text.
	 * @param string $text the text to convert
	 * @param int $maxLength the maximum slug length, must be positive
	 * @return string the slug
	 */
	public static function create(string $text, int $maxLength = 80): string
	{
		$maxLength = max(1, $maxLength);
		$slug = self::transliterate(trim($text));
		$slug = strtolower($slug);
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
		$slug = trim($slug, '-');
		if (strlen($slug) > $maxLength) {
			$slug = rtrim(substr($slug, 0, $maxLength), '-');
		}
		return $slug === '' ? self::EMPTY_SLUG : $slug;
	}

	/**
	 * Appends `-2`, `-3`, ... to a slug until `$exists` reports it as free.
	 * @param string $slug the base slug
	 * @param callable $exists `function(string $slug): bool`, true when the slug is taken
	 * @param int $maxLength the maximum slug length including the suffix
	 * @return string the unique slug
	 */
	public static function unique(string $slug, callable $exists, int $maxLength = 80): string
	{
		$base = self::create($slug, $maxLength);
		$candidate = $base;
		$i = 1;
		while ($exists($candidate)) {
			$i++;
			$suffix = '-' . $i;
			$candidate = rtrim(substr($base, 0, max(1, $maxLength - strlen($suffix))), '-') . $suffix;
		}
		return $candidate;
	}

	/**
	 * Transliterates text to ASCII.
	 * @param string $text the text
	 * @return string ASCII text, non-convertible characters removed
	 */
	private static function transliterate(string $text): string
	{
		if ($text === '') {
			return '';
		}
		if (function_exists('transliterator_transliterate')) {
			$result = @transliterator_transliterate('Any-Latin; Latin-ASCII; [^\x00-\x7F] Remove', $text);
			if (is_string($result)) {
				return $result;
			}
		}
		if (function_exists('iconv')) {
			$result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
			if (is_string($result)) {
				return $result;
			}
		}
		return preg_replace('/[^\x00-\x7F]/', '', $text) ?? '';
	}
}
