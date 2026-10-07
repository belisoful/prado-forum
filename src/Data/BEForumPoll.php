<?php

/**
 * BEForumPoll class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumPoll class.
 *
 * BEForumPoll is a vote attached to a {@see BEForumThread}.  Members choose
 * up to {@see $max_choices} of the {@see BEForumPollOption options}; the poll
 * closes explicitly or at {@see $closes_at}.
 *
 * @property-read BEForumThread $thread the thread (lazy)
 * @property-read BEForumPollOption[] $options the options (lazy)
 * @property-read BEForumPollVote[] $votes the votes (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPoll extends BEForumRecord
{
	public const TABLE_NAME = 'polls';
	public const BOOLEAN_COLUMNS = ['is_closed', 'allow_revote'];

	public static $RELATIONS = [
		'thread' => [self::BELONGS_TO, BEForumThread::class, 'thread_id'],
		'options' => [self::HAS_MANY, BEForumPollOption::class, 'poll_id'],
		'votes' => [self::HAS_MANY, BEForumPollVote::class, 'poll_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the thread */
	public $thread_id;
	/** @var string the question */
	public $question;
	/** @var int the maximum number of options a member may choose */
	public $max_choices = 1;
	/** @var null|string the automatic closing time */
	public $closes_at;
	/** @var bool|int whether the poll has been closed explicitly */
	public $is_closed = false;
	/** @var bool|int whether members may change their vote */
	public $allow_revote = false;
	/** @var int number of voting members */
	public $vote_count = 0;
	/** @var null|string creation time */
	public $created_at;
	/** @var null|string last update time */
	public $updated_at;

	/**
	 * @return bool whether voting is closed, explicitly or by the closing time
	 */
	public function getIsClosed(): bool
	{
		if ($this->flag('is_closed')) {
			return true;
		}
		$closes = BEForumTime::parse($this->closes_at);
		return $closes !== null && $closes <= BEForumTime::timestamp();
	}

	/**
	 * @return bool whether members may change their vote
	 */
	public function getAllowRevote(): bool
	{
		return $this->flag('allow_revote');
	}

	/**
	 * @return bool whether more than one option may be chosen
	 */
	public function getIsMultipleChoice(): bool
	{
		return (int) $this->max_choices > 1;
	}
}
