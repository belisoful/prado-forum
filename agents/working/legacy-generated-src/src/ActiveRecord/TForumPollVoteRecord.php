<?php

/**
 * TForumPollVoteRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumPollVoteRecord records a single user's choice within a poll.
 *
 * In a multiple-choice poll a user may have one TForumPollVoteRecord per
 * option they selected (up to the poll's max_choices limit).
 *
 * @property int    $id
 * @property int    $poll_id
 * @property int    $option_id
 * @property int    $user_id
 * @property string $created_at
 *
 * Relations:
 * @property TForumPollRecord        $poll
 * @property TForumPollOptionRecord  $option
 * @property TForumUserProfileRecord $user
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPollVoteRecord extends TActiveRecord
{
    const TABLE = 'forum_poll_votes';

    public $id;
    public $poll_id;
    public $option_id;
    public $user_id;
    public $created_at;

    public static $RELATIONS = [
        'poll'   => [self::BELONGS_TO, TForumPollRecord::class,        'poll_id'],
        'option' => [self::BELONGS_TO, TForumPollOptionRecord::class,  'option_id'],
        'user'   => [self::BELONGS_TO, TForumUserProfileRecord::class, 'user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
