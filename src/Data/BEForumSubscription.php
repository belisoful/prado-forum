<?php

/**
 * BEForumSubscription class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumSubscription class.
 *
 * BEForumSubscription records that a {@see BEForumMember} follows a board
 * (new threads) or a thread (new replies).  Activity on the target produces
 * {@see BEForumNotification notifications}.
 *
 * @property-read BEForumMember $member the member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSubscription extends BEForumRecord
{
	public const TABLE_NAME = 'subscriptions';

	public const TYPE_BOARD = 'board';
	public const TYPE_THREAD = 'thread';

	public static $RELATIONS = [
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the subscribing member */
	public $member_id;
	/** @var string the target type: board or thread */
	public $target_type;
	/** @var int the target id */
	public $target_id;
	/** @var null|string creation time */
	public $created_at;

	/**
	 * @return string[] the valid target types
	 */
	public static function getTypes(): array
	{
		return [self::TYPE_BOARD, self::TYPE_THREAD];
	}
}
