<?php

/**
 * BEForumBookmark class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumBookmark class.
 *
 * BEForumBookmark saves a {@see BEForumPost} in the personal list of a
 * {@see BEForumMember}.
 *
 * @property-read BEForumMember $member the member (lazy)
 * @property-read BEForumPost $post the post (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBookmark extends BEForumRecord
{
	public const TABLE_NAME = 'bookmarks';

	public static $RELATIONS = [
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
		'post' => [self::BELONGS_TO, BEForumPost::class, 'post_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the member */
	public $member_id;
	/** @var int the post */
	public $post_id;
	/** @var null|string creation time */
	public $created_at;
}
