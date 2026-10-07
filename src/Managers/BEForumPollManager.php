<?php

/**
 * BEForumPollManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPoll;
use Belisoful\Forum\Data\BEForumPollOption;
use Belisoful\Forum\Data\BEForumPollVote;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumTime;

/**
 * BEForumPollManager class.
 *
 * BEForumPollManager attaches polls to threads and records votes.  A poll
 * allows up to `max_choices` options per member, may close at a time or be
 * closed by the thread owner or a moderator, and optionally allows members
 * to change their vote.
 *
 * ```php
 * $poll = $forum->getPolls()->createPoll($thread, ['question' => 'Tabs or spaces?', 'options' => ['Tabs', 'Spaces']]);
 * $forum->getPolls()->vote($poll, [$optionId]);
 * $results = $forum->getPolls()->getResults($poll);
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPollManager extends BEForumManager
{
	/** The maximum number of options of a poll */
	public const MAX_OPTIONS = 20;

	/**
	 * Creates the poll of a thread.
	 * @param BEForumThread $thread the thread
	 * @param array<string, mixed> $definition question, options (string[]), max_choices, closes_at, allow_revote
	 * @param bool $authorize whether to check the poll permission (false while creating the thread)
	 * @throws BEForumValidationException when the definition is invalid or the thread has a poll
	 * @return BEForumPoll the poll
	 */
	public function createPoll(BEForumThread $thread, array $definition, bool $authorize = true): BEForumPoll
	{
		$module = $this->getModule();
		if (!$module->getEnablePolls()) {
			throw new BEForumValidationException('poll', 'forum_polls_disabled');
		}
		if ($authorize) {
			$this->authorize(BEForumPermissions::POLL_CREATE, $this->extraFor((int) $thread->board_id));
			$this->authorize(BEForumPermissions::THREAD_EDIT, $this->extraFor((int) $thread->board_id, $thread->member_id ? $module->getMembers()->findById((int) $thread->member_id)?->username : null));
		}
		$this->getDbConnection();
		if ($this->findPollOfThread($thread) !== null) {
			throw new BEForumValidationException('poll', 'forum_poll_exists');
		}
		$question = $this->validateText('question', (string) ($definition['question'] ?? ''), 1, 255, 'forum_poll_question_required', 'forum_field_too_long');
		$labels = [];
		foreach ((array) ($definition['options'] ?? []) as $label) {
			$label = trim((string) $label);
			if ($label !== '' && !in_array($label, $labels, true)) {
				$labels[] = mb_substr($label, 0, 255);
			}
		}
		if (count($labels) < 2) {
			throw new BEForumValidationException('options', 'forum_poll_options_required');
		}
		if (count($labels) > self::MAX_OPTIONS) {
			throw new BEForumValidationException('options', 'forum_poll_too_many_options', self::MAX_OPTIONS);
		}
		$maxChoices = max(1, min((int) ($definition['max_choices'] ?? 1), count($labels)));
		$closesAt = null;
		if (!empty($definition['closes_at'])) {
			$stamp = BEForumTime::parse((string) $definition['closes_at']);
			if ($stamp === null || $stamp <= BEForumTime::timestamp()) {
				throw new BEForumValidationException('closes_at', 'forum_poll_closes_invalid');
			}
			$closesAt = BEForumTime::format($stamp);
		}
		return $this->transaction(function () use ($thread, $question, $labels, $maxChoices, $closesAt, $definition): BEForumPoll {
			$poll = new BEForumPoll();
			$poll->thread_id = $thread->getId();
			$poll->question = $question;
			$poll->max_choices = $maxChoices;
			$poll->closes_at = $closesAt;
			$poll->allow_revote = !empty($definition['allow_revote']);
			$poll->is_closed = false;
			$poll->save();
			foreach ($labels as $index => $label) {
				$option = new BEForumPollOption();
				$option->poll_id = $poll->getId();
				$option->position = $index + 1;
				$option->label = $label;
				$option->save();
			}
			$this->flushRequestCache();
			$this->raise('onPollCreated', $poll, ['thread' => $thread]);
			return $poll;
		});
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return null|BEForumPoll the poll of the thread
	 */
	public function findPollOfThread(BEForumThread $thread): ?BEForumPoll
	{
		return $this->cached('poll:thread:' . $thread->getId(), function () use ($thread): ?BEForumPoll {
			$this->getDbConnection();
			$poll = BEForumPoll::finder()->find('thread_id = ?', [$thread->getId()]);
			return $poll instanceof BEForumPoll ? $poll : null;
		});
	}

	/**
	 * @param BEForumPoll $poll the poll
	 * @return BEForumPollOption[] the options in position order
	 */
	public function getOptions(BEForumPoll $poll): array
	{
		$this->getDbConnection();
		return BEForumPollOption::finder()->findAll(BEForumPollOption::criteria('poll_id = ?', [$poll->getId()], ['position' => 'asc']));
	}

	/**
	 * @param BEForumPoll $poll the poll
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return int[] the option ids the member voted for
	 */
	public function getMemberVotes(BEForumPoll $poll, ?BEForumMember $member = null): array
	{
		$member ??= $this->getMember();
		if ($member === null) {
			return [];
		}
		$this->getDbConnection();
		return array_map(fn (BEForumPollVote $vote) => (int) $vote->option_id, BEForumPollVote::finder()->findAll('poll_id = ? AND member_id = ?', [$poll->getId(), $member->getId()]));
	}

	/**
	 * @param BEForumPoll $poll the poll
	 * @param null|BEForumMember $member the member, null for the current member
	 * @return bool whether the member has voted
	 */
	public function hasVoted(BEForumPoll $poll, ?BEForumMember $member = null): bool
	{
		return $this->getMemberVotes($poll, $member) !== [];
	}

	/**
	 * @param BEForumPoll $poll the poll
	 * @return bool whether the current member may vote now
	 */
	public function canVote(BEForumPoll $poll): bool
	{
		if ($poll->getIsClosed() || $this->getMember() === null) {
			return false;
		}
		if ($this->hasVoted($poll) && !$poll->getAllowRevote()) {
			return false;
		}
		$thread = $this->getModule()->getThreads()->findThread((int) $poll->thread_id);
		return $this->can(BEForumPermissions::POLL_VOTE, $this->extraFor($thread ? (int) $thread->board_id : null));
	}

	/**
	 * Records the vote of the current member.
	 * @param BEForumPoll $poll the poll
	 * @param int[] $optionIds the chosen option ids
	 * @throws BEForumValidationException when the poll is closed, the member voted already, or the options are invalid
	 * @return BEForumPollVote[] the votes
	 */
	public function vote(BEForumPoll $poll, array $optionIds): array
	{
		$module = $this->getModule();
		$thread = $module->getThreads()->getThread((int) $poll->thread_id);
		$this->authorize(BEForumPermissions::POLL_VOTE, $this->extraFor((int) $thread->board_id));
		$member = $this->requireMember(BEForumPermissions::POLL_VOTE);
		if ($poll->getIsClosed()) {
			throw new BEForumValidationException('poll', 'forum_poll_closed');
		}
		$optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
		if (!$optionIds) {
			throw new BEForumValidationException('options', 'forum_poll_choice_required');
		}
		if (count($optionIds) > (int) $poll->max_choices) {
			throw new BEForumValidationException('options', 'forum_poll_too_many_choices', (int) $poll->max_choices);
		}
		$options = $this->indexById($this->getOptions($poll));
		foreach ($optionIds as $optionId) {
			if (!isset($options[$optionId])) {
				throw new BEForumValidationException('options', 'forum_poll_option_invalid', $optionId);
			}
		}
		return $this->transaction(function () use ($poll, $member, $optionIds): array {
			$previous = $this->getMemberVotes($poll, $member);
			if ($previous) {
				if (!$poll->getAllowRevote()) {
					throw new BEForumValidationException('poll', 'forum_poll_already_voted');
				}
				BEForumPollVote::finder()->deleteAll('poll_id = ? AND member_id = ?', [$poll->getId(), $member->getId()]);
				foreach ($previous as $optionId) {
					BEForumPollOption::execute('UPDATE {table} SET vote_count = vote_count - 1 WHERE id = :id AND vote_count > 0', ['id' => $optionId]);
				}
				BEForumPoll::execute('UPDATE {table} SET vote_count = vote_count - 1 WHERE id = :id AND vote_count > 0', ['id' => $poll->getId()]);
			}
			$votes = [];
			foreach ($optionIds as $optionId) {
				$vote = new BEForumPollVote();
				$vote->poll_id = $poll->getId();
				$vote->option_id = $optionId;
				$vote->member_id = $member->getId();
				$vote->save();
				$votes[] = $vote;
				BEForumPollOption::execute('UPDATE {table} SET vote_count = vote_count + 1 WHERE id = :id', ['id' => $optionId]);
			}
			BEForumPoll::execute('UPDATE {table} SET vote_count = vote_count + 1 WHERE id = :id', ['id' => $poll->getId()]);
			$poll->vote_count = (int) $poll->vote_count + ($previous ? 0 : 1);
			$this->flushRequestCache();
			$this->raise('onPollVoted', $poll, ['options' => $optionIds, 'revote' => (bool) $previous], $member);
			return $votes;
		});
	}

	/**
	 * Returns the results of a poll.
	 * @param BEForumPoll $poll the poll
	 * @return array{options: array<int, array{option: BEForumPollOption, votes: int, percent: float}>, total_votes: int, voters: int}
	 */
	public function getResults(BEForumPoll $poll): array
	{
		$options = $this->getOptions($poll);
		$total = 0;
		foreach ($options as $option) {
			$total += (int) $option->vote_count;
		}
		$rows = [];
		foreach ($options as $option) {
			$rows[(int) $option->getId()] = ['option' => $option, 'votes' => (int) $option->vote_count, 'percent' => $option->getPercentage($total)];
		}
		return ['options' => $rows, 'total_votes' => $total, 'voters' => (int) $poll->vote_count];
	}

	/**
	 * Closes or reopens a poll.
	 * @param BEForumPoll $poll the poll
	 * @param bool $closed whether the poll is closed
	 * @return BEForumPoll the poll
	 */
	public function setClosed(BEForumPoll $poll, bool $closed): BEForumPoll
	{
		$poll->refresh();
		$module = $this->getModule();
		$thread = $module->getThreads()->getThread((int) $poll->thread_id);
		$this->authorize(BEForumPermissions::THREAD_EDIT, $this->extraFor((int) $thread->board_id, $thread->member_id ? $module->getMembers()->findById((int) $thread->member_id)?->username : null));
		$poll->is_closed = $closed;
		if (!$closed && $poll->closes_at !== null && BEForumTime::parse($poll->closes_at) <= BEForumTime::timestamp()) {
			$poll->closes_at = null;
		}
		$poll->save();
		$this->flushRequestCache();
		return $poll;
	}

	/**
	 * Deletes the poll of a thread with its options and votes.
	 * @param BEForumThread $thread the thread
	 * @return bool whether a poll was deleted
	 */
	public function deletePollOfThread(BEForumThread $thread): bool
	{
		$poll = $this->findPollOfThread($thread);
		if ($poll === null) {
			return false;
		}
		BEForumPollVote::finder()->deleteAll('poll_id = ?', [$poll->getId()]);
		BEForumPollOption::finder()->deleteAll('poll_id = ?', [$poll->getId()]);
		$poll->delete();
		$this->flushRequestCache();
		return true;
	}
}
