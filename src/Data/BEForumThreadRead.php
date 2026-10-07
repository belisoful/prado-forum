<?php

/**
 * BEForumThreadRead class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumThreadRead class.
 *
 * BEForumThreadRead remembers the last post a {@see BEForumMember} has read
 * in a {@see BEForumThread}, which drives the unread markers and the
 * "jump to first unread post" links.
 *
 * @property-read BEForumMember $member the member (lazy)
 * @property-read BEForumThread $thread the thread (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadRead extends BEForumRecord
{
	public const TABLE_NAME = 'thread_reads';

	public static $RELATIONS = [
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
		'thread' => [self::BELONGS_TO, BEForumThread::class, 'thread_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the member */
	public $member_id;
	/** @var int the thread */
	public $thread_id;
	/** @var null|int the last read post */
	public $last_read_post_id;
	/** @var null|string the last read time */
	public $read_at;

	/**
	 * Stamps `read_at` on insertion.
	 * @param \Prado\Data\ActiveRecord\TActiveRecordChangeEventParameter $param the event parameter
	 */
	public function onInsert($param)
	{
		if (!$this->read_at) {
			$this->read_at = BEForumTime::now();
		}
		parent::onInsert($param);
	}
}
