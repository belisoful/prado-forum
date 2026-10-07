<?php

/**
 * TForumBoardRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumBoardRecord represents a row in the `forum_boards` table.
 *
 * A Board (also called a "forum" in phpBB terminology) is a sub-area inside
 * a Category.  Boards hold Threads.  They track aggregate counters (thread_count,
 * post_count) and a pointer to the most recent post for quick display.
 *
 * @property int         $id
 * @property int         $category_id
 * @property int|null    $parent_id       sub-board support (NULL = top-level)
 * @property string      $name
 * @property string|null $description
 * @property string|null $icon_url
 * @property int         $sort_order
 * @property int         $is_active
 * @property int         $is_locked       locked boards accept no new threads
 * @property int         $thread_count
 * @property int         $post_count
 * @property int|null    $last_post_id
 * @property int|null    $last_post_user_id
 * @property string|null $last_post_at
 * @property string|null $permissions_json JSON-encoded permission overrides
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Relations:
 * @property TForumCategoryRecord      $category
 * @property TForumBoardRecord|null    $parent
 * @property TForumBoardRecord[]       $children
 * @property TForumThreadRecord[]      $threads
 * @property TForumPostRecord|null     $lastPost
 * @property TForumUserProfileRecord|null $lastPostUser
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumBoardRecord extends TActiveRecord
{
    const TABLE = 'forum_boards';

    public $id;
    public $category_id;
    public $parent_id;
    public $name;
    public $description;
    public $icon_url;
    public $sort_order = 0;
    public $is_active  = 1;
    public $is_locked  = 0;
    public $thread_count = 0;
    public $post_count   = 0;
    public $last_post_id;
    public $last_post_user_id;
    public $last_post_at;
    public $permissions_json;
    public $created_at;
    public $updated_at;

    public static $RELATIONS = [
        'category'     => [self::BELONGS_TO, TForumCategoryRecord::class,    'category_id'],
        'parent'       => [self::BELONGS_TO, TForumBoardRecord::class,        'parent_id'],
        'children'     => [self::HAS_MANY,   TForumBoardRecord::class,        'parent_id'],
        'threads'      => [self::HAS_MANY,   TForumThreadRecord::class,       'board_id'],
        'lastPost'     => [self::BELONGS_TO, TForumPostRecord::class,         'last_post_id'],
        'lastPostUser' => [self::BELONGS_TO, TForumUserProfileRecord::class,  'last_post_user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
