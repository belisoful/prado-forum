<?php

/**
 * BEForumTime class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Util;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Prado\Prado;

/**
 * BEForumTime class.
 *
 * BEForumTime is the single clock of the forum.  All timestamps are stored as
 * UTC `Y-m-d H:i:s` strings.  The clock can be frozen with {@see freeze} which
 * makes time dependent logic (edit windows, flood control, poll closing)
 * deterministic in unit tests.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
final class BEForumTime
{
	/** The storage format of every forum timestamp */
	public const FORMAT = 'Y-m-d H:i:s';

	/** @var null|int a frozen unix timestamp, null uses the system clock */
	private static ?int $_frozen = null;

	/**
	 * @return int the current unix timestamp
	 */
	public static function timestamp(): int
	{
		return self::$_frozen ?? time();
	}

	/**
	 * @return string the current UTC time in storage format
	 */
	public static function now(): string
	{
		return self::format(self::timestamp());
	}

	/**
	 * Freezes the clock at a unix timestamp; null resumes the system clock.
	 * @param null|int $timestamp the frozen unix timestamp
	 */
	public static function freeze(?int $timestamp): void
	{
		self::$_frozen = $timestamp;
	}

	/**
	 * Formats a unix timestamp in storage format (UTC).
	 * @param int $timestamp the unix timestamp
	 * @return string the formatted time
	 */
	public static function format(int $timestamp): string
	{
		return gmdate(self::FORMAT, $timestamp);
	}

	/**
	 * Parses a storage format (or any strtotime compatible) UTC time to a unix timestamp.
	 * @param null|DateTimeInterface|int|string $time the time
	 * @return null|int the unix timestamp, null when empty or unparsable
	 */
	public static function parse($time): ?int
	{
		if ($time === null || $time === '') {
			return null;
		}
		if ($time instanceof DateTimeInterface) {
			return $time->getTimestamp();
		}
		if (is_int($time)) {
			return $time;
		}
		if (is_numeric($time)) {
			return (int) $time;
		}
		$date = DateTimeImmutable::createFromFormat('!' . self::FORMAT, (string) $time, new DateTimeZone('UTC'));
		if ($date instanceof DateTimeImmutable) {
			return $date->getTimestamp();
		}
		$result = strtotime((string) $time . ' UTC', self::timestamp());
		return $result === false ? null : $result;
	}

	/**
	 * Returns a UTC time a number of seconds from now in storage format.
	 * @param int $seconds seconds to add, may be negative
	 * @return string the formatted time
	 */
	public static function fromNow(int $seconds): string
	{
		return self::format(self::timestamp() + $seconds);
	}

	/**
	 * @param null|string $time a storage format time
	 * @return int seconds elapsed since the time, 0 when the time is empty
	 */
	public static function age(?string $time): int
	{
		$stamp = self::parse($time);
		return $stamp === null ? 0 : max(0, self::timestamp() - $stamp);
	}

	/**
	 * Formats a stored time as a localized, human readable relative phrase such as "3 minutes ago".
	 * @param null|string $time a storage format time
	 * @return string the phrase, empty when the time is empty
	 */
	public static function relative(?string $time): string
	{
		$stamp = self::parse($time);
		if ($stamp === null) {
			return '';
		}
		$delta = self::timestamp() - $stamp;
		$future = $delta < 0;
		$delta = abs($delta);
		$units = [
			[31536000, 'year', 'years'],
			[2592000, 'month', 'months'],
			[604800, 'week', 'weeks'],
			[86400, 'day', 'days'],
			[3600, 'hour', 'hours'],
			[60, 'minute', 'minutes'],
		];
		foreach ($units as [$size, $singular, $plural]) {
			if ($delta >= $size) {
				$count = (int) floor($delta / $size);
				$unit = Prado::localize($count === 1 ? $singular : $plural);
				return $future
					? Prado::localize('in {0} {1}', [$count, $unit])
					: Prado::localize('{0} {1} ago', [$count, $unit]);
			}
		}
		return Prado::localize('just now');
	}

	/**
	 * Formats a stored time for display.
	 * @param null|string $time a storage format time
	 * @param string $format a PHP date format
	 * @param null|string $timezone a timezone identifier, null for UTC
	 * @return string the formatted time, empty when the time is empty
	 */
	public static function display(?string $time, string $format = 'Y-m-d H:i', ?string $timezone = null): string
	{
		$stamp = self::parse($time);
		if ($stamp === null) {
			return '';
		}
		$date = (new DateTimeImmutable('@' . $stamp));
		if ($timezone) {
			try {
				$date = $date->setTimezone(new DateTimeZone($timezone));
			} catch (\Exception $e) {
				// invalid timezone identifiers fall back to UTC
			}
		}
		return $date->format($format);
	}
}
