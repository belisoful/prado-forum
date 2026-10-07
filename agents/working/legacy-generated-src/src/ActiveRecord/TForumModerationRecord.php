<?php

/**
 * TForumModerationRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumModerationRecord is an immutable audit log of moderation actions.
 *
 * Whenever a moderator or admin takes an action — delete a post, lock a
 * thread, ban a user, approve a queued post — a record is inserted here.
 * Records are never updated or deleted so the log remains trustworthy.
 *
 * Action types: 'approve_post', 'reject_post', 'delete_post', 'restore_post',
 * 'edit_post', 'lock_thread', 'unlock_thread', 'pin_thread', 'unpin_thread',
 * 'delete_thread', 'restore_thread', 'move_thread', 'merge_threads',
 * 'split_thread', 'ban_user', 'unban_user', 'warn_user', 'edit_user'.
 *
 * @property int         $id
 * @property int         $moderator_user_id
 * @property string      $action            action type slug
 * @property string      $target_type       'post' | 'thread' | 'user' | 'board'
 * @property int         $target_id
 * @property string|null $reason
 * @property string|null $meta_json         JSON blob for action-specific data
 * @property string      $created_at
 *
 * Relations:
 * @property TForumUserProfileRecord $moderator
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumModerationRecord extends TActiveRecord
{
    const TABLE = 'forum_moderation_log';

    public $id;
    public $moderator_user_id;
    public $action;
    public $target_type;
    public $target_id;
    public $reason;
    public $meta_json;
    public $created_at;

    public static $RELATIONS = [
        'moderator' => [self::BELONGS_TO, TForumUserProfileRecord::class, 'moderator_user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
