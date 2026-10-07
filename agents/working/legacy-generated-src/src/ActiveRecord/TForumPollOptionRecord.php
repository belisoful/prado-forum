<?php

/**
 * TForumPollOptionRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumPollOptionRecord is a single answer choice within a poll.
 *
 * @property int    $id
 * @property int    $poll_id
 * @property string $option_text
 * @property int    $sort_order
 * @property int    $vote_count   denormalised counter
 * @property string $created_at
 *
 * Relations:
 * @property TForumPollRecord      $poll
 * @property TForumPollVoteRecord[] $votes
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPollOptionRecord extends TActiveRecord
{
    const TABLE = 'forum_poll_options';

    public $id;
    public $poll_id;
    public $option_text;
    public $sort_order  = 0;
    public $vote_count  = 0;
    public $created_at;

    public static $RELATIONS = [
        'poll'  => [self::BELONGS_TO, TForumPollRecord::class,     'poll_id'],
        'votes' => [self::HAS_MANY,   TForumPollVoteRecord::class, 'option_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
