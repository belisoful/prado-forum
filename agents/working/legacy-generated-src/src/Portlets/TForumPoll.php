<?php

/**
 * TForumPoll class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumPollRecord;
use Belisoful\Forum\ActiveRecord\TForumPollVoteRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * TForumPoll displays the poll attached to a thread (if any) and
 * handles vote submission.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPoll extends TForumControl
{
    private $_threadId = 0;

    /** @var TForumPollRecord|null */
    private $_poll = null;

    /** @var int[] option IDs voted for by the current user */
    private $_userVotes = [];

    /** @var string[] validation errors */
    private $_errors = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);
        if ($this->_threadId > 0 && $this->getForumManager()->getEnablePolls()) {
            $this->_poll = TForumPollRecord::finder()->with('options', 'votes')->find(
                'thread_id = ?', [$this->_threadId]
            );
            if ($this->_poll) {
                $this->loadUserVotes();
            }
        }
    }

    private function loadUserVotes(): void
    {
        $profile = $this->resolveCurrentProfile();
        if (!$profile || !$this->_poll) {
            return;
        }

        $votes = TForumPollVoteRecord::finder()->findAll(
            'poll_id = :pid AND user_id = :uid',
            [':pid' => $this->_poll->id, ':uid' => $profile->id]
        ) ?: [];

        $this->_userVotes = array_map(fn($v) => (int) $v->option_id, $votes);
    }

    public function submitVote($sender, $param): void
    {
        if (!$this->_poll || !$this->_poll->isOpen()) {
            return;
        }

        $profile = $this->resolveCurrentProfile();
        if (!$profile) {
            $this->_errors[] = 'You must be logged in to vote.';
            return;
        }
        if (!empty($this->_userVotes) && !$this->_poll->allow_change_vote) {
            $this->_errors[] = 'You have already voted and cannot change your vote.';
            return;
        }

        $request   = $this->getRequest();
        $optionIds = (array) $request->getParam('poll_option', []);
        $optionIds = array_map('intval', $optionIds);

        if (empty($optionIds)) {
            $this->_errors[] = 'Please select at least one option.';
            return;
        }

        $max = (int) $this->_poll->max_choices;
        if (!$this->_poll->is_multiple_choice) {
            $optionIds = [$optionIds[0]];
        } elseif ($max > 0 && count($optionIds) > $max) {
            $this->_errors[] = 'You may select at most ' . $max . ' options.';
            return;
        }

        // Remove old votes if changing vote is allowed.
        TForumPollVoteRecord::finder()->deleteAll(
            'poll_id = :pid AND user_id = :uid',
            [':pid' => $this->_poll->id, ':uid' => $profile->id]
        );

        // Insert new votes.
        foreach ($optionIds as $optId) {
            $vote            = new TForumPollVoteRecord();
            $vote->poll_id   = $this->_poll->id;
            $vote->option_id = $optId;
            $vote->user_id   = $profile->id;
            $vote->created_at = date('Y-m-d H:i:s');
            $vote->save();

            // Increment denormalised counter.
            $db = $this->getForumManager()->getDbConnection();
            $db->createCommand(
                "UPDATE {$this->getForumManager()->getTable('poll_options')} SET vote_count = vote_count + 1 WHERE id = :id"
            )->bindValue(':id', $optId)->execute();
        }

        $this->_userVotes = $optionIds;
        $this->_poll      = TForumPollRecord::finder()->with('options', 'votes')->findByPk($this->_poll->id);
    }

    // ===================================================================
    // Properties
    // ===================================================================


    public function getThreadId(): int { return $this->_threadId; }
    public function setThreadId(int $v): void { $this->_threadId = $v; }

    public function getPoll(): ?TForumPollRecord { return $this->_poll; }
    public function hasPoll(): bool { return $this->_poll !== null; }
    public function getUserVotes(): array { return $this->_userVotes; }
    public function hasVoted(): bool { return !empty($this->_userVotes); }
    public function getErrors(): array { return $this->_errors; }

    public function getVotePercent(int $optionVotes, int $totalVotes): float
    {
        return $totalVotes > 0 ? round($optionVotes / $totalVotes * 100, 1) : 0.0;
    }

    public function getTotalVotes(): int
    {
        if (!$this->_poll) {
            return 0;
        }
        return array_sum(array_map(fn($o) => (int) $o->vote_count, $this->_poll->options ?? []));
    }

    private function resolveCurrentProfile(): ?TForumUserProfileRecord
    {
        $users = $this->getApplication()->getModule('users');
        if (!$users) {
            return null;
        }
        $user = $users->getUser();
        if (!$user || $user->getIsGuest()) {
            return null;
        }
        return TForumUserProfileRecord::finder()->findByAttributes(['username' => $user->getName()]);
    }

}
