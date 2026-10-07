<?php

/**
 * BEForumPollVote class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumPollVote class.
 *
 * BEForumPollVote records that a {@see BEForumMember} chose one
 * {@see BEForumPollOption} of a {@see BEForumPoll}.  Multiple choice polls
 * create one vote per chosen option.
 *
 * @property-read BEForumPoll $poll the poll (lazy)
 * @property-read BEForumPollOption $option the option (lazy)
 * @property-read BEForumMember $member the member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPollVote extends BEForumRecord
{
	public const TABLE_NAME = 'poll_votes';

	public static $RELATIONS = [
		'poll' => [self::BELONGS_TO, BEForumPoll::class, 'poll_id'],
		'option' => [self::BELONGS_TO, BEForumPollOption::class, 'option_id'],
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the poll */
	public $poll_id;
	/** @var int the chosen option */
	public $option_id;
	/** @var int the voting member */
	public $member_id;
	/** @var null|string creation time */
	public $created_at;
}
