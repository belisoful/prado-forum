<?php

/**
 * BEForumNotification class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumNotification class.
 *
 * BEForumNotification is an in-forum message for a {@see BEForumMember}: a
 * reply in a subscribed thread, a mention, a reaction, an accepted answer, a
 * moderation action or an awarded badge.  {@see $data} carries type specific
 * details (thread id, post id, titles) as JSON.
 *
 * @property-read BEForumMember $member the recipient (lazy)
 * @property-read null|BEForumMember $actor the member who caused the notification (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumNotification extends BEForumRecord
{
	public const TABLE_NAME = 'notifications';
	public const BOOLEAN_COLUMNS = ['is_read'];
	public const JSON_COLUMNS = ['data'];

	public const TYPE_REPLY = 'reply';
	public const TYPE_THREAD = 'thread';
	public const TYPE_MENTION = 'mention';
	public const TYPE_QUOTE = 'quote';
	public const TYPE_REACTION = 'reaction';
	public const TYPE_ACCEPTED = 'accepted';
	public const TYPE_MODERATION = 'moderation';
	public const TYPE_BADGE = 'badge';
	public const TYPE_REPORT = 'report';

	public const TARGET_THREAD = 'thread';
	public const TARGET_POST = 'post';
	public const TARGET_MEMBER = 'member';
	public const TARGET_BOARD = 'board';

	public static $RELATIONS = [
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
		'actor' => [self::BELONGS_TO, BEForumMember::class, 'actor_member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the recipient member */
	public $member_id;
	/** @var string the notification type */
	public $type;
	/** @var null|int the member who caused the notification */
	public $actor_member_id;
	/** @var null|string the target type */
	public $target_type;
	/** @var null|int the target id */
	public $target_id;
	/** @var null|string JSON details */
	public $data;
	/** @var bool|int whether the notification has been read */
	public $is_read = false;
	/** @var null|string the read time */
	public $read_at;
	/** @var null|string creation time */
	public $created_at;

	/**
	 * @return bool whether the notification has been read
	 */
	public function getIsRead(): bool
	{
		return $this->flag('is_read');
	}

	/**
	 * @return array the decoded details
	 */
	public function getDataArray(): array
	{
		return $this->getJsonColumn('data');
	}

	/**
	 * @param string $key the detail name
	 * @param mixed $default the value when unset
	 * @return mixed the detail value
	 */
	public function getDetail(string $key, $default = null)
	{
		return $this->getDataArray()[$key] ?? $default;
	}
}
