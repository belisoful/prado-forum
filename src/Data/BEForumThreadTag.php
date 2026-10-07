<?php

/**
 * BEForumThreadTag class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumThreadTag class.
 *
 * BEForumThreadTag links one {@see BEForumTag} to one {@see BEForumThread}.
 *
 * @property-read BEForumThread $thread the thread (lazy)
 * @property-read BEForumTag $tag the tag (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadTag extends BEForumRecord
{
	public const TABLE_NAME = 'thread_tags';

	public static $RELATIONS = [
		'thread' => [self::BELONGS_TO, BEForumThread::class, 'thread_id'],
		'tag' => [self::BELONGS_TO, BEForumTag::class, 'tag_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the thread */
	public $thread_id;
	/** @var int the tag */
	public $tag_id;
	/** @var null|string creation time */
	public $created_at;
}
