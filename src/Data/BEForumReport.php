<?php

/**
 * BEForumReport class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumReport class.
 *
 * BEForumReport is a member's complaint about a {@see BEForumPost}.  Reports
 * are open until a moderator resolves or dismisses them.
 *
 * @property-read BEForumPost $post the reported post (lazy)
 * @property-read null|BEForumMember $reporter the reporting member (lazy)
 * @property-read null|BEForumMember $handler the moderator who handled the report (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumReport extends BEForumRecord
{
	public const TABLE_NAME = 'reports';

	public const STATUS_OPEN = 'open';
	public const STATUS_RESOLVED = 'resolved';
	public const STATUS_DISMISSED = 'dismissed';

	public static $RELATIONS = [
		'post' => [self::BELONGS_TO, BEForumPost::class, 'post_id'],
		'reporter' => [self::BELONGS_TO, BEForumMember::class, 'reporter_member_id'],
		'handler' => [self::BELONGS_TO, BEForumMember::class, 'handled_by_member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the reported post */
	public $post_id;
	/** @var null|int the reporting member */
	public $reporter_member_id;
	/** @var string the reason */
	public $reason;
	/** @var string the status */
	public $status = self::STATUS_OPEN;
	/** @var null|int the moderator who handled the report */
	public $handled_by_member_id;
	/** @var null|string the handling time */
	public $handled_at;
	/** @var null|string the moderator resolution note */
	public $resolution;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return string[] the valid statuses
	 */
	public static function getStatuses(): array
	{
		return [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_DISMISSED];
	}

	/**
	 * @return bool whether the report awaits moderation
	 */
	public function getIsOpen(): bool
	{
		return $this->status === self::STATUS_OPEN;
	}
}
