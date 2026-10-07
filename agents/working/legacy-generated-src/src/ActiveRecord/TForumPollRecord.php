<?php

/**
 * TForumPollRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumPollRecord represents the poll attached to a thread.
 *
 * Each thread may have at most one poll.  The poll contains ordered options
 * (TForumPollOptionRecord) and tracks votes (TForumPollVoteRecord).
 * Polls can optionally be open-ended (no expiry) or time-limited.
 *
 * @property int         $id
 * @property int         $thread_id
 * @property string      $question
 * @property int         $is_multiple_choice  1 = users may pick >1 option
 * @property int         $max_choices         when multiple, the limit (0 = unlimited)
 * @property int         $is_anonymous        1 = individual votes are hidden
 * @property int         $allow_change_vote   1 = users can revise their vote
 * @property string|null $closes_at           NULL = never closes
 * @property int         $is_closed
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Relations:
 * @property TForumThreadRecord      $thread
 * @property TForumPollOptionRecord[] $options
 * @property TForumPollVoteRecord[]   $votes
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPollRecord extends TActiveRecord
{
    const TABLE = 'forum_polls';

    public $id;
    public $thread_id;
    public $question;
    public $is_multiple_choice = 0;
    public $max_choices        = 1;
    public $is_anonymous       = 0;
    public $allow_change_vote  = 1;
    public $closes_at;
    public $is_closed          = 0;
    public $created_at;
    public $updated_at;

    public static $RELATIONS = [
        'thread'  => [self::BELONGS_TO, TForumThreadRecord::class,      'thread_id'],
        'options' => [self::HAS_MANY,   TForumPollOptionRecord::class,  'poll_id'],
        'votes'   => [self::HAS_MANY,   TForumPollVoteRecord::class,    'poll_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }

    /**
     * Determine whether the poll is accepting new votes.
     */
    public function isOpen(): bool
    {
        if ($this->is_closed) {
            return false;
        }
        if ($this->closes_at !== null && strtotime($this->closes_at) <= time()) {
            return false;
        }
        return true;
    }
}
