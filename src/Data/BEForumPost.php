<?php

/**
 * BEForumPost class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumPost class.
 *
 * BEForumPost is one message inside a {@see BEForumThread}.  {@see $content}
 * holds the raw text in {@see $content_format} (markdown, text) and
 * {@see $content_html} the rendered, sanitised HTML.  {@see $position} is the
 * 1-based number of the post within its thread; position 1 is the opening
 * post.  Posts may reply to another post, await approval, be soft deleted and
 * keep an edit history in {@see BEForumPostRevision revisions}.
 *
 * @property-read BEForumThread $thread the thread (lazy)
 * @property-read BEForumBoard $board the board (lazy)
 * @property-read null|BEForumMember $author the author (lazy)
 * @property-read null|BEForumPost $replyTo the post replied to (lazy)
 * @property-read BEForumPostRevision[] $revisions the edit history (lazy)
 * @property-read BEForumAttachment[] $attachments the attachments (lazy)
 * @property-read BEForumReaction[] $reactions the reactions (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPost extends BEForumRecord
{
	public const TABLE_NAME = 'posts';
	public const BOOLEAN_COLUMNS = ['is_approved', 'is_deleted'];

	public static $RELATIONS = [
		'thread' => [self::BELONGS_TO, BEForumThread::class, 'thread_id'],
		'board' => [self::BELONGS_TO, BEForumBoard::class, 'board_id'],
		'author' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
		'replyTo' => [self::BELONGS_TO, BEForumPost::class, 'reply_to_post_id'],
		'revisions' => [self::HAS_MANY, BEForumPostRevision::class, 'post_id'],
		'attachments' => [self::HAS_MANY, BEForumAttachment::class, 'post_id'],
		'reactions' => [self::HAS_MANY, BEForumReaction::class, 'post_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the thread */
	public $thread_id;
	/** @var int the board, denormalised from the thread */
	public $board_id;
	/** @var null|int the author member, null for guests */
	public $member_id;
	/** @var null|string the guest author name */
	public $guest_name;
	/** @var null|int the post being replied to */
	public $reply_to_post_id;
	/** @var int the 1-based number of the post within the thread */
	public $position = 0;
	/** @var string the raw content */
	public $content;
	/** @var string the content format */
	public $content_format = 'markdown';
	/** @var null|string the rendered HTML */
	public $content_html;
	/** @var null|string the author IP address */
	public $ip_address;
	/** @var bool|int whether the post has been approved by moderation */
	public $is_approved = true;
	/** @var bool|int whether the post is soft deleted */
	public $is_deleted = false;
	/** @var null|string the soft deletion time */
	public $deleted_at;
	/** @var null|int the member who deleted the post */
	public $deleted_by_member_id;
	/** @var int number of edits */
	public $edit_count = 0;
	/** @var null|string the last edit time */
	public $edited_at;
	/** @var null|int the member who last edited the post */
	public $edited_by_member_id;
	/** @var int number of reactions */
	public $reaction_count = 0;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return bool whether the post has been approved by moderation
	 */
	public function getIsApproved(): bool
	{
		return $this->flag('is_approved');
	}

	/**
	 * @return bool whether the post is soft deleted
	 */
	public function getIsDeleted(): bool
	{
		return $this->flag('is_deleted');
	}

	/**
	 * @return bool whether the post has been edited
	 */
	public function getIsEdited(): bool
	{
		return (int) $this->edit_count > 0;
	}

	/**
	 * @return bool whether the post opens its thread
	 */
	public function getIsFirstPost(): bool
	{
		return (int) $this->position === 1;
	}

	/**
	 * @return bool whether the post replies to a specific post
	 */
	public function getIsReply(): bool
	{
		return $this->reply_to_post_id !== null && (int) $this->reply_to_post_id > 0;
	}

	/**
	 * @return string the author display name, the guest name for guest posts
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

	/**
	 * @param int $length the maximum excerpt length
	 * @return string a plain text excerpt of the content
	 */
	public function getExcerpt(int $length = 200): string
	{
		$text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->content_html ?? $this->content))) ?? '');
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if (mb_strlen($text) <= $length) {
			return $text;
		}
		return rtrim(mb_substr($text, 0, max(1, $length - 1))) . '…';
	}
}
