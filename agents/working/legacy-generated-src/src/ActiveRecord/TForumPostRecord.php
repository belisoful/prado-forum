<?php

/**
 * TForumPostRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumPostRecord represents a row in the `forum_posts` table.
 *
 * A Post is a single message within a Thread.  The very first post in a thread
 * (`is_first_post = 1`) contains the opening body text.  Posts carry both the
 * original raw source (BBCode / Markdown) and the pre-rendered HTML so display
 * is fast.  Soft-delete is supported via `deleted_at`.
 *
 * @property int         $id
 * @property int         $thread_id
 * @property int         $board_id       denormalised for fast board-level queries
 * @property int         $user_id
 * @property string      $content_raw    original markup (BBCode / Markdown)
 * @property string      $content_html   rendered, sanitised HTML
 * @property int         $is_first_post
 * @property int         $is_approved
 * @property int         $is_spam_flagged
 * @property int         $spam_score
 * @property int|null    $edited_by_user_id
 * @property string|null $edited_at
 * @property string|null $edit_reason
 * @property string|null $ip_address     poster IP at submission time
 * @property string|null $user_agent
 * @property string|null $deleted_at
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Relations:
 * @property TForumThreadRecord        $thread
 * @property TForumUserProfileRecord   $author
 * @property TForumUserProfileRecord|null $editedBy
 * @property TForumPostHistoryRecord[] $history
 * @property TForumReactionRecord[]    $reactions
 * @property TForumAttachmentRecord[]  $attachments
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPostRecord extends TActiveRecord
{
    const TABLE = 'forum_posts';

    public $id;
    public $thread_id;
    public $board_id;
    public $user_id;
    public $content_raw;
    public $content_html;
    public $is_first_post    = 0;
    public $is_approved      = 1;
    public $is_spam_flagged  = 0;
    public $spam_score       = 0;
    public $edited_by_user_id;
    public $edited_at;
    public $edit_reason;
    public $ip_address;
    public $user_agent;
    public $deleted_at;
    public $created_at;
    public $updated_at;

    public static $RELATIONS = [
        'thread'      => [self::BELONGS_TO, TForumThreadRecord::class,       'thread_id'],
        'author'      => [self::BELONGS_TO, TForumUserProfileRecord::class,  'user_id'],
        'editedBy'    => [self::BELONGS_TO, TForumUserProfileRecord::class,  'edited_by_user_id'],
        'history'     => [self::HAS_MANY,   TForumPostHistoryRecord::class,  'post_id'],
        'reactions'   => [self::HAS_MANY,   TForumReactionRecord::class,     'post_id'],
        'attachments' => [self::HAS_MANY,   TForumAttachmentRecord::class,   'post_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
