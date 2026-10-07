<?php

/**
 * TForumThreadRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumThreadRecord represents a row in the `forum_threads` table.
 *
 * A Thread is a topic started by a user within a Board.  The first post of
 * the thread carries the opening body text.  Threads track view counts,
 * reply counts, pinned/locked/approved flags, soft-delete, and a pointer to
 * the most recent reply.
 *
 * @property int         $id
 * @property int         $board_id
 * @property int         $user_id
 * @property string      $title
 * @property int         $is_pinned
 * @property int         $is_locked
 * @property int         $is_approved       0 = in moderation queue
 * @property int         $is_sticky         sticky threads always appear first
 * @property int         $view_count
 * @property int         $reply_count
 * @property int|null    $last_post_id
 * @property int|null    $last_post_user_id
 * @property string|null $last_post_at
 * @property string|null $deleted_at        NULL = not deleted (soft-delete)
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Relations:
 * @property TForumBoardRecord         $board
 * @property TForumUserProfileRecord   $author
 * @property TForumPostRecord[]        $posts
 * @property TForumPostRecord|null     $firstPost
 * @property TForumPostRecord|null     $lastPost
 * @property TForumTagRecord[]         $tags
 * @property TForumPollRecord|null     $poll
 * @property TForumSubscriptionRecord[] $subscriptions
 * @property TForumAttachmentRecord[]  $attachments
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumThreadRecord extends TActiveRecord
{
    const TABLE = 'forum_threads';

    public $id;
    public $board_id;
    public $user_id;
    public $title;
    public $is_pinned    = 0;
    public $is_locked    = 0;
    public $is_approved  = 1;
    public $is_sticky    = 0;
    public $view_count   = 0;
    public $reply_count  = 0;
    public $last_post_id;
    public $last_post_user_id;
    public $last_post_at;
    public $deleted_at;
    public $created_at;
    public $updated_at;

    public static $RELATIONS = [
        'board'         => [self::BELONGS_TO, TForumBoardRecord::class,        'board_id'],
        'author'        => [self::BELONGS_TO, TForumUserProfileRecord::class,  'user_id'],
        'posts'         => [self::HAS_MANY,   TForumPostRecord::class,         'thread_id'],
        'firstPost'     => [self::HAS_ONE,    TForumPostRecord::class,         'thread_id'],
        'lastPost'      => [self::BELONGS_TO, TForumPostRecord::class,         'last_post_id'],
        'tags'          => [self::MANY_TO_MANY, TForumTagRecord::class,        'forum_thread_tags(thread_id, tag_id)'],
        'poll'          => [self::HAS_ONE,    TForumPollRecord::class,         'thread_id'],
        'subscriptions' => [self::HAS_MANY,   TForumSubscriptionRecord::class, 'thread_id'],
        'attachments'   => [self::HAS_MANY,   TForumAttachmentRecord::class,   'thread_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
