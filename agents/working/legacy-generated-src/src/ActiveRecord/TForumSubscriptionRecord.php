<?php

/**
 * TForumSubscriptionRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumSubscriptionRecord allows users to "watch" boards or threads.
 *
 * Exactly one of `thread_id` / `board_id` will be set.
 * `notify_on` controls the granularity: 'all' = every new post,
 * 'mention' = only when @mentioned, 'digest' = daily summary email.
 *
 * @property int      $id
 * @property int      $user_id
 * @property int|null $thread_id
 * @property int|null $board_id
 * @property string   $notify_on   'all' | 'mention' | 'digest'
 * @property string   $created_at
 *
 * Relations:
 * @property TForumUserProfileRecord  $user
 * @property TForumThreadRecord|null  $thread
 * @property TForumBoardRecord|null   $board
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumSubscriptionRecord extends TActiveRecord
{
    const TABLE = 'forum_subscriptions';

    public $id;
    public $user_id;
    public $thread_id;
    public $board_id;
    public $notify_on  = 'all';
    public $created_at;

    public static $RELATIONS = [
        'user'   => [self::BELONGS_TO, TForumUserProfileRecord::class, 'user_id'],
        'thread' => [self::BELONGS_TO, TForumThreadRecord::class,      'thread_id'],
        'board'  => [self::BELONGS_TO, TForumBoardRecord::class,       'board_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
