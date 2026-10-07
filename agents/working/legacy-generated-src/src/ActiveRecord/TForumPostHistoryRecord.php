<?php

/**
 * TForumPostHistoryRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumPostHistoryRecord stores every previous revision of a post body.
 *
 * When a post is edited the old content is appended here before the post
 * row is updated, giving moderators and admins a full audit trail.
 *
 * @property int         $id
 * @property int         $post_id
 * @property int         $edited_by_user_id
 * @property string      $content_raw       content before the edit
 * @property string      $content_html
 * @property string|null $edit_reason
 * @property int         $revision_number
 * @property string      $created_at        when this history entry was recorded
 *
 * Relations:
 * @property TForumPostRecord          $post
 * @property TForumUserProfileRecord   $editor
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPostHistoryRecord extends TActiveRecord
{
    const TABLE = 'forum_post_history';

    public $id;
    public $post_id;
    public $edited_by_user_id;
    public $content_raw;
    public $content_html;
    public $edit_reason;
    public $revision_number = 1;
    public $created_at;

    public static $RELATIONS = [
        'post'   => [self::BELONGS_TO, TForumPostRecord::class,         'post_id'],
        'editor' => [self::BELONGS_TO, TForumUserProfileRecord::class,  'edited_by_user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
