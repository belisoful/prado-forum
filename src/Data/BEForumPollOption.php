<?php

/**
 * BEForumPollOption class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumPollOption class.
 *
 * BEForumPollOption is one answer of a {@see BEForumPoll} with its
 * denormalised {@see $vote_count}.
 *
 * @property-read BEForumPoll $poll the poll (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPollOption extends BEForumRecord
{
	public const TABLE_NAME = 'poll_options';

	public static $RELATIONS = [
		'poll' => [self::BELONGS_TO, BEForumPoll::class, 'poll_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the poll */
	public $poll_id;
	/** @var int sort position */
	public $position = 0;
	/** @var string the answer text */
	public $label;
	/** @var int number of votes */
	public $vote_count = 0;

	/**
	 * @param int $totalVotes the total votes of the poll
	 * @return float the share of votes in percent, 0 when there are no votes
	 */
	public function getPercentage(int $totalVotes): float
	{
		if ($totalVotes <= 0) {
			return 0.0;
		}
		return round((int) $this->vote_count * 100 / $totalVotes, 1);
	}
}
