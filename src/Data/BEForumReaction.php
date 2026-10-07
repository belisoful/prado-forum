<?php

/**
 * BEForumReaction class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumReaction class.
 *
 * BEForumReaction records that a {@see BEForumMember} reacted to a
 * {@see BEForumPost} with a reaction {@see $type} (like, love, ...).  The
 * available types are configured on the module.
 *
 * @property-read BEForumPost $post the post (lazy)
 * @property-read BEForumMember $member the member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumReaction extends BEForumRecord
{
	public const TABLE_NAME = 'reactions';

	public static $RELATIONS = [
		'post' => [self::BELONGS_TO, BEForumPost::class, 'post_id'],
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the post */
	public $post_id;
	/** @var int the reacting member */
	public $member_id;
	/** @var string the reaction type */
	public $type;
	/** @var null|string creation time */
	public $created_at;
}
