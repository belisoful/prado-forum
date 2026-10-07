<?php

/**
 * BEForumBadge class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumBadge class.
 *
 * BEForumBadge is an award that can be given to members
 * ({@see BEForumMemberBadge}) by moderators or by host application logic
 * listening to forum events.
 *
 * @property-read BEForumMemberBadge[] $memberBadges the awards (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBadge extends BEForumRecord
{
	public const TABLE_NAME = 'badges';

	public static $RELATIONS = [
		'memberBadges' => [self::HAS_MANY, BEForumMemberBadge::class, 'badge_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var string URL slug */
	public $slug;
	/** @var string display name */
	public $name;
	/** @var null|string description */
	public $description;
	/** @var null|string an icon URL or CSS class */
	public $icon;
	/** @var null|string creation time */
	public $created_at;
}
