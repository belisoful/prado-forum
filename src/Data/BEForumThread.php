<?php

/**
 * BEForumThread class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumThread class.
 *
 * BEForumThread is a discussion (topic) inside a {@see BEForumBoard}.  It is
 * made of {@see BEForumPost posts}; the first post carries the opening
 * content.  Threads have a {@see $type} (discussion, question, announcement),
 * may be pinned (optionally until a time), locked, awaiting approval or soft
 * deleted, and a question may have an {@see $accepted_post_id accepted answer}.
 * Counters and last post information are denormalised for listings.
 *
 * @property-read BEForumBoard $board the board (lazy)
 * @property-read null|BEForumMember $author the author (lazy)
 * @property-read null|BEForumPost $firstPost the first post (lazy)
 * @property-read null|BEForumPost $lastPost the last post (lazy)
 * @property-read null|BEForumMember $lastPoster the member of the last post (lazy)
 * @property-read BEForumPost[] $posts all posts (lazy)
 * @property-read null|BEForumPoll $poll the poll (lazy)
 * @property-read BEForumThreadTag[] $threadTags the tag assignments (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThread extends BEForumRecord
{
	public const TABLE_NAME = 'threads';
	public const BOOLEAN_COLUMNS = ['is_pinned', 'is_locked', 'is_approved', 'is_deleted'];

	public const TYPE_DISCUSSION = 'discussion';
	public const TYPE_QUESTION = 'question';
	public const TYPE_ANNOUNCEMENT = 'announcement';

	public static $RELATIONS = [
		'board' => [self::BELONGS_TO, BEForumBoard::class, 'board_id'],
		'author' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
		'firstPost' => [self::BELONGS_TO, BEForumPost::class, 'first_post_id'],
		'lastPost' => [self::BELONGS_TO, BEForumPost::class, 'last_post_id'],
		'lastPoster' => [self::BELONGS_TO, BEForumMember::class, 'last_poster_member_id'],
		'posts' => [self::HAS_MANY, BEForumPost::class, 'thread_id'],
		'poll' => [self::HAS_ONE, BEForumPoll::class, 'thread_id'],
		'threadTags' => [self::HAS_MANY, BEForumThreadTag::class, 'thread_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the board */
	public $board_id;
	/** @var null|int the author member, null for guests */
	public $member_id;
	/** @var null|string the guest author name */
	public $guest_name;
	/** @var string URL slug, unique within the board */
	public $slug;
	/** @var string the title */
	public $title;
	/** @var string the thread type */
	public $type = self::TYPE_DISCUSSION;
	/** @var bool|int whether the thread is pinned to the top of the board */
	public $is_pinned = false;
	/** @var bool|int whether replies are refused */
	public $is_locked = false;
	/** @var bool|int whether the thread has been approved by moderation */
	public $is_approved = true;
	/** @var bool|int whether the thread is soft deleted */
	public $is_deleted = false;
	/** @var null|int the accepted answer post */
	public $accepted_post_id;
	/** @var int number of views */
	public $view_count = 0;
	/** @var int number of replies (posts minus the first) */
	public $reply_count = 0;
	/** @var null|int the first post */
	public $first_post_id;
	/** @var null|int the last post */
	public $last_post_id;
	/** @var null|string the time of the last post */
	public $last_post_at;
	/** @var null|int the member of the last post */
	public $last_poster_member_id;
	/** @var null|string the end of the pin */
	public $pinned_until;
	/** @var null|string the soft deletion time */
	public $deleted_at;
	/** @var null|int the member who deleted the thread */
	public $deleted_by_member_id;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return string[] the valid thread types
	 */
	public static function getTypes(): array
	{
		return [self::TYPE_DISCUSSION, self::TYPE_QUESTION, self::TYPE_ANNOUNCEMENT];
	}

	/**
	 * @return bool whether the thread is pinned and the pin has not expired
	 */
	public function getIsPinned(): bool
	{
		if (!$this->flag('is_pinned')) {
			return false;
		}
		$until = BEForumTime::parse($this->pinned_until);
		return $until === null || $until > BEForumTime::timestamp();
	}

	/**
	 * @return bool whether replies are refused
	 */
	public function getIsLocked(): bool
	{
		return $this->flag('is_locked');
	}

	/**
	 * @return bool whether the thread has been approved by moderation
	 */
	public function getIsApproved(): bool
	{
		return $this->flag('is_approved');
	}

	/**
	 * @return bool whether the thread is soft deleted
	 */
	public function getIsDeleted(): bool
	{
		return $this->flag('is_deleted');
	}

	/**
	 * @return bool whether the thread is a question with an accepted answer
	 */
	public function getIsSolved(): bool
	{
		return $this->accepted_post_id !== null && (int) $this->accepted_post_id > 0;
	}

	/**
	 * @return bool whether the thread is a question
	 */
	public function getIsQuestion(): bool
	{
		return $this->type === self::TYPE_QUESTION;
	}

	/**
	 * @return int the number of posts including the first post
	 */
	public function getPostCount(): int
	{
		return (int) $this->reply_count + 1;
	}

	/**
	 * @return string the author display name, the guest name for guest threads
	 */
	public function getAuthorName(): string
	{
		if ($this->member_id !== null && (int) $this->member_id > 0) {
			$author = $this->author;
			if ($author instanceof BEForumMember) {
				return $author->getDisplayName();
			}
		}
		return (string) ($this->guest_name ?? '');
	}
}
