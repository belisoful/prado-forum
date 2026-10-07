<?php

/**
 * BEForumTag class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumTag class.
 *
 * BEForumTag is a label applied to threads through {@see BEForumThreadTag}.
 * {@see $thread_count} is denormalised for tag clouds.
 *
 * @property-read BEForumThreadTag[] $threadTags the thread assignments (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumTag extends BEForumRecord
{
	public const TABLE_NAME = 'tags';

	public static $RELATIONS = [
		'threadTags' => [self::HAS_MANY, BEForumThreadTag::class, 'tag_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var string URL slug */
	public $slug;
	/** @var string display name */
	public $name;
	/** @var int number of threads carrying the tag */
	public $thread_count = 0;
	/** @var null|string creation time */
	public $created_at;
}
