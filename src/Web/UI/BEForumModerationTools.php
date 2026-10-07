<?php

/**
 * BEForumModerationTools class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumModerationTools class.
 *
 * BEForumModerationTools offers the thread level actions: editing the title,
 * type and tags (owner and moderators), and for moderators lock/unlock,
 * pin/unpin (optionally until a date), move to another board, approve,
 * delete, restore and purge.  It hides itself when the user has no action.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumModerationTools ThreadID=<%= $this->Request['thread'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLinkButton $Edit
 * @property \Prado\Web\UI\WebControls\TLinkButton $ToggleLock
 * @property \Prado\Web\UI\WebControls\TLinkButton $TogglePin
 * @property \Prado\Web\UI\WebControls\TTextBox $PinUntil
 * @property \Prado\Web\UI\WebControls\TLinkButton $Approve
 * @property \Prado\Web\UI\WebControls\TLinkButton $Delete
 * @property \Prado\Web\UI\WebControls\TLinkButton $Restore
 * @property \Prado\Web\UI\WebControls\TLinkButton $Purge
 * @property \Prado\Web\UI\WebControls\TPanel $MovePanel
 * @property \Prado\Web\UI\WebControls\TDropDownList $MoveTarget
 * @property \Prado\Web\UI\WebControls\TPanel $EditPanel
 * @property \Prado\Web\UI\WebControls\TTextBox $Title
 * @property \Prado\Web\UI\WebControls\TDropDownList $Type
 * @property \Prado\Web\UI\WebControls\TLabel $TagsLabel
 * @property \Prado\Web\UI\WebControls\TTextBox $Tags
 * @property \Prado\Web\UI\WebControls\TButton $Save
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumModerationTools extends BEForumControl
{
	/** @var null|BEForumThread the thread */
	private ?BEForumThread $_thread = null;

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
		$this->_thread = null;
	}

	/**
	 * @return null|BEForumThread the thread
	 */
	public function getThread(): ?BEForumThread
	{
		if ($this->_thread === null && $this->getThreadID() > 0) {
			$this->_thread = $this->getForum()->getThreads()->findThread($this->getThreadID());
		}
		return $this->_thread;
	}

	/**
	 * Reloads the thread after an action.
	 */
	protected function refresh(): void
	{
		$this->getForum()->getThreads()->flushRequestCache();
		$this->_thread = null;
	}

	/**
	 * Runs a thread action and redirects to the thread on success.
	 * @param callable $action `function(BEForumThread $thread): ?string` returning an optional redirect URL
	 */
	protected function act(callable $action): void
	{
		$thread = $this->getThread();
		if ($thread === null) {
			return;
		}
		$this->attempt(function () use ($thread, $action): void {
			$url = $action($thread);
			$this->refresh();
			$this->redirect($url ?? $this->getUrls()->thread($this->getThreadID()));
		});
	}

	/**
	 * Locks or unlocks the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function toggleLockClicked($sender, $param): void
	{
		$this->act(fn (BEForumThread $thread) => $this->getForum()->getThreads()->setLocked($thread, !$thread->getIsLocked()) ? null : null);
	}

	/**
	 * Pins or unpins the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function togglePinClicked($sender, $param): void
	{
		$until = trim((string) $this->PinUntil->getText());
		$this->act(function (BEForumThread $thread) use ($until): ?string {
			$this->getForum()->getThreads()->setPinned($thread, !$thread->getIsPinned(), $until !== '' ? str_replace('T', ' ', $until) : null);
			return null;
		});
	}

	/**
	 * Moves the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function moveClicked($sender, $param): void
	{
		$target = (int) $this->MoveTarget->getSelectedValue();
		$this->act(function (BEForumThread $thread) use ($target): ?string {
			$this->getForum()->getThreads()->moveThread($thread, $target);
			return null;
		});
	}

	/**
	 * Approves the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function approveClicked($sender, $param): void
	{
		$this->act(function (BEForumThread $thread): ?string {
			$this->getForum()->getThreads()->approveThread($thread);
			return null;
		});
	}

	/**
	 * Soft deletes the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function deleteClicked($sender, $param): void
	{
		$this->act(function (BEForumThread $thread): ?string {
			$this->getForum()->getThreads()->deleteThread($thread);
			return $this->getIsModerator((int) $thread->board_id) ? null : $this->getUrls()->board((int) $thread->board_id);
		});
	}

	/**
	 * Restores the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function restoreClicked($sender, $param): void
	{
		$this->act(function (BEForumThread $thread): ?string {
			$this->getForum()->getThreads()->restoreThread($thread);
			return null;
		});
	}

	/**
	 * Permanently removes the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function purgeClicked($sender, $param): void
	{
		$this->act(function (BEForumThread $thread): ?string {
			$boardId = (int) $thread->board_id;
			$this->getForum()->getThreads()->purgeThread($thread);
			return $this->getUrls()->board($boardId);
		});
	}

	/**
	 * Shows the edit form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function editClicked($sender, $param): void
	{
		$thread = $this->getThread();
		if ($thread === null) {
			return;
		}
		$this->Title->setText((string) $thread->title);
		$this->Type->setSelectedValue((string) $thread->type);
		$tags = [];
		foreach ($this->getForum()->getTags()->getThreadTags($thread) as $tag) {
			$tags[] = (string) $tag->name;
		}
		$this->Tags->setText(implode(', ', $tags));
		$this->EditPanel->setVisible(true);
	}

	/**
	 * Saves the edit form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function saveClicked($sender, $param): void
	{
		$fields = [
			'title' => (string) $this->Title->getText(),
			'type' => (string) $this->Type->getSelectedValue(),
		];
		if ($this->getForum()->getEnableTags()) {
			$fields['tags'] = $this->getForum()->getTags()->parseList((string) $this->Tags->getText());
		}
		$this->EditPanel->setVisible(true);
		$this->act(function (BEForumThread $thread) use ($fields): ?string {
			$this->getForum()->getThreads()->updateThread($thread, $fields);
			return null;
		});
	}

	/**
	 * Hides the edit form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function cancelEditClicked($sender, $param): void
	{
		$this->EditPanel->setVisible(false);
	}

	/**
	 * Configures the actions for the current user.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$thread = $this->getThread();
		$forum = $this->getForum();
		if ($thread === null) {
			$this->setVisible(false);
			return;
		}
		$threads = $forum->getThreads();
		$moderator = $this->getIsModerator((int) $thread->board_id);
		$canEdit = $threads->canEdit($thread);
		$canDelete = $threads->canDelete($thread);
		$this->setVisible($moderator || $canEdit || $canDelete);
		$this->Edit->setVisible($canEdit && !$thread->getIsDeleted());
		$this->ToggleLock->setVisible($moderator);
		$this->ToggleLock->setText($this->te($thread->getIsLocked() ? 'Unlock' : 'Lock'));
		$this->TogglePin->setVisible($moderator);
		$this->TogglePin->setText($this->te($thread->getIsPinned() ? 'Unpin' : 'Pin'));
		$this->PinUntil->setVisible($moderator && !$thread->getIsPinned());
		$this->Approve->setVisible($moderator && !$thread->getIsApproved());
		$this->Delete->setVisible($canDelete && !$thread->getIsDeleted());
		$this->Restore->setVisible($moderator && $thread->getIsDeleted());
		$this->Purge->setVisible($moderator && $thread->getIsDeleted());
		$this->MovePanel->setVisible($moderator);
		if ($moderator) {
			$targets = [];
			foreach ($forum->getBoards()->getBoards(true) as $board) {
				if ($board->getId() !== (int) $thread->board_id) {
					$targets[(int) $board->getId()] = $this->e((string) $board->name);
				}
			}
			$this->MoveTarget->setDataSource($targets);
			$this->MoveTarget->dataBind();
			$this->MovePanel->setVisible(count($targets) > 0);
		}
		$types = [BEForumThread::TYPE_DISCUSSION => $this->te('Discussion'), BEForumThread::TYPE_QUESTION => $this->te('Question')];
		if ($moderator || $thread->type === BEForumThread::TYPE_ANNOUNCEMENT) {
			$types[BEForumThread::TYPE_ANNOUNCEMENT] = $this->te('Announcement');
		}
		$selected = (string) $this->Type->getSelectedValue();
		$this->Type->setDataSource($types);
		$this->Type->dataBind();
		if ($selected !== '' && isset($types[$selected])) {
			$this->Type->setSelectedValue($selected);
		}
		$this->Tags->setVisible($forum->getEnableTags());
		$this->TagsLabel->setVisible($forum->getEnableTags());
	}
}
