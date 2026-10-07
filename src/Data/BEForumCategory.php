<?php

/**
 * BEForumCategory class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumCategory class.
 *
 * BEForumCategory is a top level grouping of {@see BEForumBoard boards}.
 * Categories are ordered by {@see $position} and may be hidden from the index.
 *
 * @property-read BEForumBoard[] $boards the boards of the category (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumCategory extends BEForumRecord
{
	public const TABLE_NAME = 'categories';
	public const BOOLEAN_COLUMNS = ['is_hidden'];

	public static $RELATIONS = [
		'boards' => [self::HAS_MANY, BEForumBoard::class, 'category_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var string URL slug */
	public $slug;
	/** @var string display name */
	public $name;
	/** @var null|string description (raw content) */
	public $description;
	/** @var int sort position */
	public $position = 0;
	/** @var bool|int whether the category is hidden from the index */
	public $is_hidden = false;
	/** @var int number of boards */
	public $board_count = 0;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return bool whether the category is hidden from the index
	 */
	public function getIsHidden(): bool
	{
		return $this->flag('is_hidden');
	}
}
