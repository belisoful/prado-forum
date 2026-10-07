<?php

/**
 * TForumCategoryRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumCategoryRecord represents a row in the `forum_categories` table.
 *
 * Categories are the top-level grouping containers.  Each category holds one
 * or more boards (sub-areas).  Categories have no threading of their own.
 *
 * Schema: {@see src/DB/ForumSchemaMySQL.sql}
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string|null $icon_url
 * @property int         $sort_order
 * @property int         $is_active
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Relations:
 * @property TForumBoardRecord[] $boards
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumCategoryRecord extends TActiveRecord
{
    const TABLE = 'forum_categories';

    /** @var int primary key */
    public $id;
    /** @var string display name */
    public $name;
    /** @var string|null optional description */
    public $description;
    /** @var string|null URL of a decorative icon */
    public $icon_url;
    /** @var int display order (lower = higher on page) */
    public $sort_order = 0;
    /** @var int 1 = visible to users, 0 = hidden */
    public $is_active = 1;
    /** @var string creation timestamp */
    public $created_at;
    /** @var string last-update timestamp */
    public $updated_at;

    /** @var array Active Record relation declarations */
    public static $RELATIONS = [
        'boards' => [self::HAS_MANY, TForumBoardRecord::class, 'category_id'],
    ];

    /**
     * @param string $className leave as default
     * @return static
     */
    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
