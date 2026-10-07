<?php

/**
 * BEForumMemberBadge class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumMemberBadge class.
 *
 * BEForumMemberBadge records that a {@see BEForumBadge} has been awarded to a
 * {@see BEForumMember}.
 *
 * @property-read BEForumMember $member the member (lazy)
 * @property-read BEForumBadge $badge the badge (lazy)
 * @property-read null|BEForumMember $awardedBy the awarding member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumMemberBadge extends BEForumRecord
{
	public const TABLE_NAME = 'member_badges';

	public static $RELATIONS = [
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
		'badge' => [self::BELONGS_TO, BEForumBadge::class, 'badge_id'],
		'awardedBy' => [self::BELONGS_TO, BEForumMember::class, 'awarded_by_member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the member */
	public $member_id;
	/** @var int the badge */
	public $badge_id;
	/** @var null|int the awarding member */
	public $awarded_by_member_id;
	/** @var null|string the award time */
	public $awarded_at;

	/**
	 * Stamps `awarded_at` on insertion.
	 * @param \Prado\Data\ActiveRecord\TActiveRecordChangeEventParameter $param the event parameter
	 */
	public function onInsert($param)
	{
		if (!$this->awarded_at) {
			$this->awarded_at = BEForumTime::now();
		}
		parent::onInsert($param);
	}
}
