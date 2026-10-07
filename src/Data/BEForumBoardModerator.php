<?php

/**
 * BEForumBoardModerator class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumBoardModerator class.
 *
 * BEForumBoardModerator assigns a {@see BEForumMember} as moderator of one
 * {@see BEForumBoard}.  {@see \Belisoful\Forum\Security\BEForumModeratorRule}
 * grants such members the moderation permissions within that board.
 *
 * @property-read BEForumBoard $board the board (lazy)
 * @property-read BEForumMember $member the member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBoardModerator extends BEForumRecord
{
	public const TABLE_NAME = 'board_moderators';

	public static $RELATIONS = [
		'board' => [self::BELONGS_TO, BEForumBoard::class, 'board_id'],
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the board */
	public $board_id;
	/** @var int the member */
	public $member_id;
	/** @var null|string creation time */
	public $created_at;
}
