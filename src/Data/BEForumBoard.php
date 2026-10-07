<?php

/**
 * BEForumBoard class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumBoard class.
 *
 * BEForumBoard is a forum board (sometimes called a forum or sub-forum) that
 * belongs to a {@see BEForumCategory} and contains {@see BEForumThread threads}.
 * Boards may be nested through {@see $parent_id}, locked (no new threads),
 * hidden from listings or private (visible to authenticated members only).
 * Counters and last post information are denormalised for fast listings and
 * maintained by the managers.
 *
 * @property-read BEForumCategory $category the category (lazy)
 * @property-read null|BEForumBoard $parent the parent board (lazy)
 * @property-read BEForumBoard[] $children the sub boards (lazy)
 * @property-read BEForumThread[] $threads the threads (lazy)
 * @property-read BEForumBoardModerator[] $moderators the moderator assignments (lazy)
 * @property-read null|BEForumMember $lastPoster the member of the last post (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBoard extends BEForumRecord
{
	public const TABLE_NAME = 'boards';
	public const BOOLEAN_COLUMNS = ['is_locked', 'is_hidden', 'is_private'];

	public static $RELATIONS = [
		'category' => [self::BELONGS_TO, BEForumCategory::class, 'category_id'],
		'parent' => [self::BELONGS_TO, BEForumBoard::class, 'parent_id'],
		'children' => [self::HAS_MANY, BEForumBoard::class, 'parent_id'],
		'threads' => [self::HAS_MANY, BEForumThread::class, 'board_id'],
		'moderators' => [self::HAS_MANY, BEForumBoardModerator::class, 'board_id'],
		'lastPoster' => [self::BELONGS_TO, BEForumMember::class, 'last_poster_member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the category */
	public $category_id;
	/** @var null|int the parent board */
	public $parent_id;
	/** @var string URL slug */
	public $slug;
	/** @var string display name */
	public $name;
	/** @var null|string description (raw content) */
	public $description;
	/** @var int sort position */
	public $position = 0;
	/** @var bool|int whether new threads are refused */
	public $is_locked = false;
	/** @var bool|int whether the board is hidden from listings */
	public $is_hidden = false;
	/** @var bool|int whether only authenticated members may view the board */
	public $is_private = false;
	/** @var int number of threads */
	public $thread_count = 0;
	/** @var int number of posts */
	public $post_count = 0;
	/** @var null|int the most recently active thread */
	public $last_thread_id;
	/** @var null|int the most recent post */
	public $last_post_id;
	/** @var null|string the time of the most recent post */
	public $last_post_at;
	/** @var null|int the member of the most recent post */
	public $last_poster_member_id;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return bool whether new threads are refused
	 */
	public function getIsLocked(): bool
	{
		return $this->flag('is_locked');
	}

	/**
	 * @return bool whether the board is hidden from listings
	 */
	public function getIsHidden(): bool
	{
		return $this->flag('is_hidden');
	}

	/**
	 * @return bool whether only authenticated members may view the board
	 */
	public function getIsPrivate(): bool
	{
		return $this->flag('is_private');
	}

	/**
	 * @return bool whether the board is nested inside another board
	 */
	public function getIsSubBoard(): bool
	{
		return $this->parent_id !== null && (int) $this->parent_id > 0;
	}
}
