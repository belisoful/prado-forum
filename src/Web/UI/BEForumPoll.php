<?php

/**
 * BEForumPoll class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumPoll as BEForumPollRecord;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumPoll class.
 *
 * BEForumPoll shows the poll of a thread: a vote form (radio buttons or check
 * boxes depending on the allowed choices) when the member may vote, the
 * results with percentage bars otherwise, and close/reopen actions for the
 * thread owner and moderators.  The control hides itself when the thread has
 * no poll.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumPoll ThreadID=<%= $this->Request['thread'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TPanel $VoteForm
 * @property \Prado\Web\UI\WebControls\TRadioButtonList $SingleChoice
 * @property \Prado\Web\UI\WebControls\TCheckBoxList $MultiChoice
 * @property \Prado\Web\UI\WebControls\TButton $Vote
 * @property \Prado\Web\UI\WebControls\TRepeater $Results
 * @property \Prado\Web\UI\WebControls\TLinkButton $ToggleClosed
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPoll extends BEForumControl
{
	/** @var null|BEForumPollRecord|false the poll, false when looked up and missing */
	private $_poll;

	/**
	 * @return int the thread id, read from the `thread` request parameter when unset
	 */
	public function getThreadID(): int
	{
		$id = (int) $this->getViewState('ThreadID', 0);
		return $id > 0 ? $id : $this->getRequestInt(BEForumUrlBuilder::PARAM_THREAD, 0);
	}

	/**
	 * @param int $id the thread id
	 */
	public function setThreadID($id): void
	{
		$this->setViewState('ThreadID', TPropertyValue::ensureInteger($id), 0);
		$this->_poll = null;
	}

	/**
	 * @return null|BEForumPollRecord the poll of the thread
	 */
	public function getPoll(): ?BEForumPollRecord
	{
		if ($this->_poll === null) {
			$forum = $this->getForum();
			$thread = $this->getThreadID() > 0 ? $forum->getThreads()->findThread($this->getThreadID()) : null;
			$this->_poll = ($thread !== null && $forum->getEnablePolls()) ? ($forum->getPolls()->findPollOfThread($thread) ?? false) : false;
		}
		return $this->_poll === false ? null : $this->_poll;
	}

	/**
	 * Records the vote.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function voteClicked($sender, $param): void
	{
		$poll = $this->getPoll();
		if ($poll === null) {
			return;
		}
		$options = $poll->getIsMultipleChoice() ? $this->MultiChoice->getSelectedValues() : [$this->SingleChoice->getSelectedValue()];
		$this->attempt(function () use ($poll, $options): void {
			$this->getForum()->getPolls()->vote($poll, array_map('intval', array_filter($options)));
		});
	}

	/**
	 * Closes or reopens the poll.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function toggleClosedClicked($sender, $param): void
	{
		$poll = $this->getPoll();
		if ($poll === null) {
			return;
		}
		$this->attempt(function () use ($poll): void {
			$this->getForum()->getPolls()->setClosed($poll, !$poll->getIsClosed());
		});
	}

	/**
	 * @return array<int, array{label: string, votes: int, percent: float, mine: bool}> the result rows (HTML escaped)
	 */
	public function getResultRows(): array
	{
		$poll = $this->getPoll();
		if ($poll === null) {
			return [];
		}
		$results = $this->getForum()->getPolls()->getResults($poll);
		$mine = $this->getForum()->getPolls()->getMemberVotes($poll);
		$rows = [];
		foreach ($results['options'] as $id => $row) {
			$rows[] = [
				'label' => $this->e((string) $row['option']->label),
				'votes' => (int) $row['votes'],
				'percent' => (float) $row['percent'],
				'mine' => in_array((int) $id, $mine, true),
			];
		}
		return $rows;
	}

	/**
	 * @return string the poll status line
	 */
	public function getStatusHtml(): string
	{
		$poll = $this->getPoll();
		if ($poll === null) {
			return '';
		}
		$parts = [$this->te('{0} voters', [(int) $poll->vote_count])];
		if ($poll->getIsClosed()) {
			$parts[] = $this->te('Closed');
		} elseif ($poll->closes_at) {
			$parts[] = $this->th('Closes {0}', [$this->timeTag($poll->closes_at)]);
		}
		if ($poll->getIsMultipleChoice()) {
			$parts[] = $this->te('Up to {0} choices', [(int) $poll->max_choices]);
		}
		return implode(' &middot; ', $parts);
	}

	/**
	 * Binds the vote form or the results.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$poll = $this->getPoll();
		if ($poll === null) {
			$this->setVisible(false);
			return;
		}
		$forum = $this->getForum();
		$canVote = $forum->getPolls()->canVote($poll);
		$options = [];
		foreach ($forum->getPolls()->getOptions($poll) as $option) {
			$options[(int) $option->getId()] = $this->e((string) $option->label);
		}
		$multiple = $poll->getIsMultipleChoice();
		$this->VoteForm->setVisible($canVote);
		$this->SingleChoice->setVisible($canVote && !$multiple);
		$this->MultiChoice->setVisible($canVote && $multiple);
		if ($canVote) {
			$list = $multiple ? $this->MultiChoice : $this->SingleChoice;
			$list->setDataSource($options);
			$list->dataBind();
		}
		$this->Results->setVisible(!$canVote);
		$this->bindRepeater('Results', $this->getResultRows());
		$thread = $forum->getThreads()->findThread((int) $poll->thread_id);
		$mayClose = $thread !== null && $forum->getThreads()->canEdit($thread);
		$this->ToggleClosed->setVisible($mayClose);
		$this->ToggleClosed->setText($this->te($poll->getIsClosed() ? 'Reopen poll' : 'Close poll'));
	}
}
