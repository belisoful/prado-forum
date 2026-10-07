<?php

/**
 * BEForumMember class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumMember class.
 *
 * BEForumMember is the forum profile of an application user.  The forum does
 * not authenticate users itself; it links a member to the application
 * {@see \Prado\Security\IUser} by {@see $username}.  Members carry the public
 * profile (display name, avatar, signature, bio), denormalised statistics,
 * reputation, ban state and a JSON {@see $settings} bag (notification
 * preferences, per member options).
 *
 * @property-read BEForumThread[] $threads the threads started by the member (lazy)
 * @property-read BEForumPost[] $posts the posts of the member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumMember extends BEForumRecord
{
	public const TABLE_NAME = 'members';
	public const BOOLEAN_COLUMNS = ['is_banned'];
	public const JSON_COLUMNS = ['settings'];

	public static $RELATIONS = [
		'threads' => [self::HAS_MANY, BEForumThread::class, 'member_id'],
		'posts' => [self::HAS_MANY, BEForumPost::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var string the application user name */
	public $username;
	/** @var null|string the public display name */
	public $display_name;
	/** @var null|string the e-mail address (used for gravatar and notifications) */
	public $email;
	/** @var null|string an avatar image URL */
	public $avatar_url;
	/** @var null|string the signature (raw content) */
	public $signature;
	/** @var null|string the biography (raw content) */
	public $bio;
	/** @var null|string free text location */
	public $location;
	/** @var null|string web site URL */
	public $website;
	/** @var null|string a timezone identifier for date display */
	public $timezone;
	/** @var int number of posts */
	public $post_count = 0;
	/** @var int number of threads started */
	public $thread_count = 0;
	/** @var int reputation points */
	public $reputation = 0;
	/** @var null|string the time the member joined the forum */
	public $joined_at;
	/** @var null|string the last time the member was seen */
	public $last_seen_at;
	/** @var null|string the time of the last post */
	public $last_post_at;
	/** @var bool|int whether the member is banned */
	public $is_banned = false;
	/** @var null|string the end of a temporary ban */
	public $banned_until;
	/** @var null|string the ban reason */
	public $ban_reason;
	/** @var int number of warnings received */
	public $warning_count = 0;
	/** @var null|string JSON settings */
	public $settings;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return string the display name, falling back to the user name
	 */
	public function getDisplayName(): string
	{
		$name = trim((string) $this->display_name);
		return $name === '' ? (string) $this->username : $name;
	}

	/**
	 * @return bool whether a ban is currently in effect (permanent, or temporary and not yet expired)
	 */
	public function getIsBanned(): bool
	{
		if (!$this->flag('is_banned')) {
			return false;
		}
		$until = BEForumTime::parse($this->banned_until);
		return $until === null || $until > BEForumTime::timestamp();
	}

	/**
	 * @return bool whether the ban is temporary
	 */
	public function getIsTemporaryBan(): bool
	{
		return $this->flag('is_banned') && BEForumTime::parse($this->banned_until) !== null;
	}

	/**
	 * @return string the lower cased md5 hash of the e-mail address for gravatar, empty without e-mail
	 */
	public function getEmailHash(): string
	{
		$email = strtolower(trim((string) $this->email));
		return $email === '' ? '' : md5($email);
	}

	/**
	 * Reads one member setting.
	 * @param string $key the setting name
	 * @param mixed $default the value when unset
	 * @return mixed the value
	 */
	public function getSetting(string $key, $default = null)
	{
		return $this->getJsonColumn('settings')[$key] ?? $default;
	}

	/**
	 * Writes one member setting.
	 * @param string $key the setting name
	 * @param mixed $value the value, null removes the setting
	 */
	public function setSetting(string $key, $value): void
	{
		$settings = $this->getJsonColumn('settings');
		if ($value === null) {
			unset($settings[$key]);
		} else {
			$settings[$key] = $value;
		}
		$this->setJsonColumn('settings', $settings);
	}

	/**
	 * Stamps `joined_at` on insertion.
	 * @param \Prado\Data\ActiveRecord\TActiveRecordChangeEventParameter $param the event parameter
	 */
	public function onInsert($param)
	{
		if (!$this->joined_at) {
			$this->joined_at = BEForumTime::now();
		}
		parent::onInsert($param);
	}
}
