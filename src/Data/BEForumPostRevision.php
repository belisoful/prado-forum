<?php

/**
 * BEForumPostRevision class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumPostRevision class.
 *
 * BEForumPostRevision stores the content of a {@see BEForumPost} as it was
 * before an edit, together with the editor and the edit reason, forming the
 * edit history of the post.
 *
 * @property-read BEForumPost $post the post (lazy)
 * @property-read null|BEForumMember $editor the editing member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPostRevision extends BEForumRecord
{
	public const TABLE_NAME = 'post_revisions';

	public static $RELATIONS = [
		'post' => [self::BELONGS_TO, BEForumPost::class, 'post_id'],
		'editor' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the post */
	public $post_id;
	/** @var null|int the member who made the edit */
	public $member_id;
	/** @var string the previous raw content */
	public $content;
	/** @var string the previous content format */
	public $content_format = 'markdown';
	/** @var null|string the edit reason */
	public $reason;
	/** @var null|string creation time */
	public $created_at;
}
