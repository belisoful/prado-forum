<?php

/**
 * BEForumModerationLog class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumModerationLog class.
 *
 * BEForumModerationLog is the audit trail of moderation and administration:
 * who did which {@see $action} to which target, with JSON {@see $details}.
 *
 * @property-read null|BEForumMember $moderator the acting member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumModerationLog extends BEForumRecord
{
	public const TABLE_NAME = 'moderation_log';
	public const JSON_COLUMNS = ['details'];

	public static $RELATIONS = [
		'moderator' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var null|int the acting member, null for the system */
	public $member_id;
	/** @var string the action name */
	public $action;
	/** @var string the target type */
	public $target_type;
	/** @var int the target id */
	public $target_id;
	/** @var null|string JSON details */
	public $details;
	/** @var null|string creation time */
	public $created_at;

	/**
	 * @return array the decoded details
	 */
	public function getDetailsArray(): array
	{
		return $this->getJsonColumn('details');
	}
}
