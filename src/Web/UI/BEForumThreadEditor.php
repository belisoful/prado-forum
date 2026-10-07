<?php

/**
 * BEForumThreadEditor class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumThreadEditor class.
 *
 * BEForumThreadEditor is the "new thread" form of a board: title, type,
 * tags, content with preview, attachments, an optional poll and the subscribe
 * option.  On success the browser is redirected to the new thread.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumThreadEditor BoardID=<%= $this->Request['board'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TPanel $Form
 * @property \Prado\Web\UI\WebControls\TLabel $GuestLabel
 * @property \Prado\Web\UI\WebControls\TTextBox $GuestName
 * @property \Prado\Web\UI\WebControls\TTextBox $Title
 * @property \Prado\Web\UI\WebControls\TDropDownList $Type
 * @property \Prado\Web\UI\WebControls\TLabel $TagsLabel
 * @property \Prado\Web\UI\WebControls\TTextBox $Tags
 * @property \Prado\Web\UI\WebControls\TTextBox $Content
 * @property \Prado\Web\UI\WebControls\TDropDownList $Format
 * @property \Prado\Web\UI\WebControls\TLabel $UploadLabel
 * @property \Prado\Web\UI\WebControls\TFileUpload $Upload
 * @property \Prado\Web\UI\WebControls\TPanel $PollPanel
 * @property \Prado\Web\UI\WebControls\TTextBox $PollQuestion
 * @property \Prado\Web\UI\WebControls\TTextBox $PollOptions
 * @property \Prado\Web\UI\WebControls\TTextBox $PollMaxChoices
 * @property \Prado\Web\UI\WebControls\TTextBox $PollClosesAt
 * @property \Prado\Web\UI\WebControls\TCheckBox $PollAllowRevote
 * @property \Prado\Web\UI\WebControls\TCheckBox $Subscribe
 * @property \Prado\Web\UI\WebControls\TPanel $PreviewPanel
 * @property \Prado\Web\UI\WebControls\TLiteral $Preview
 * @property \Prado\Web\UI\WebControls\TButton $Submit
 * @property \Prado\Web\UI\WebControls\TButton $PreviewButton
 * @property \Prado\Web\UI\WebControls\THyperLink $Cancel
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadEditor extends BEForumControl
{
	/** @var null|BEForumBoard the board */
	private ?BEForumBoard $_board = null;

	/**
	 * @return int the board id, read from the `board` request parameter when unset
	 */
	public function getBoardID(): int
	{
		$id = (int) $this->getViewState('BoardID', 0);
		return $id > 0 ? $id : $this->getRequestInt(BEForumUrlBuilder::PARAM_BOARD, 0);
	}

	/**
	 * @param int $id the board id
	 */
	public function setBoardID($id): void
	{
		$this->setViewState('BoardID', TPropertyValue::ensureInteger($id), 0);
		$this->_board = null;
	}

	/**
	 * @return BEForumBoard the board
	 */
	public function getBoard(): BEForumBoard
	{
		if ($this->_board === null) {
			$this->_board = $this->getForum()->getBoards()->getViewableBoard($this->getBoardID());
		}
		return $this->_board;
	}

	/**
	 * @return array<string, string> the selectable thread types keyed by value (HTML escaped labels)
	 */
	public function getTypeOptions(): array
	{
		$options = [
			BEForumThread::TYPE_DISCUSSION => $this->te('Discussion'),
			BEForumThread::TYPE_QUESTION => $this->te('Question'),
		];
		if ($this->getIsModerator($this->getBoardID())) {
			$options[BEForumThread::TYPE_ANNOUNCEMENT] = $this->te('Announcement');
		}
		return $options;
	}

	/**
	 * Configures the form.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$forum = $this->getForum();
		$board = $this->getBoard();
		$this->setPageTitle($this->t('New thread in {0}', [(string) $board->name]));
		if (!$this->getPage()->getIsPostBack()) {
			$this->Type->setDataSource($this->getTypeOptions());
			$this->Type->dataBind();
			$formats = [];
			foreach ($forum->getRenderer()->getFormats() as $format) {
				$formats[$format] = $this->e(ucfirst($format));
			}
			$this->Format->setDataSource($formats);
			$this->Format->dataBind();
			$this->Format->setSelectedValue($forum->getContentFormat());
			$this->Subscribe->setChecked(true);
			$this->PollMaxChoices->setText('1');
		}
	}

	/**
	 * Renders a preview of the content.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function previewClicked($sender, $param): void
	{
		$html = $this->getForum()->renderContent((string) $this->Content->getText(), (string) $this->Format->getSelectedValue());
		$this->Preview->setText($html);
		$this->PreviewPanel->setVisible($html !== '');
	}

	/**
	 * Creates the thread.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function submitClicked($sender, $param): void
	{
		if (!$this->getPage()->getIsValid()) {
			return;
		}
		$forum = $this->getForum();
		$this->attempt(function () use ($forum): void {
			$options = [
				'type' => (string) $this->Type->getSelectedValue(),
				'tags' => $forum->getEnableTags() ? $forum->getTags()->parseList((string) $this->Tags->getText()) : [],
				'guest_name' => (string) $this->GuestName->getText(),
				'format' => (string) $this->Format->getSelectedValue(),
				'subscribe' => $this->Subscribe->getChecked(),
			];
			$question = trim((string) $this->PollQuestion->getText());
			if ($question !== '' && $forum->getEnablePolls() && $this->PollPanel->getVisible()) {
				$options['poll'] = [
					'question' => $question,
					'options' => preg_split('/\R/', (string) $this->PollOptions->getText()) ?: [],
					'max_choices' => (int) $this->PollMaxChoices->getText(),
					'closes_at' => trim((string) $this->PollClosesAt->getText()) !== '' ? str_replace('T', ' ', trim((string) $this->PollClosesAt->getText())) : null,
					'allow_revote' => $this->PollAllowRevote->getChecked(),
				];
			}
			$thread = $forum->getThreads()->createThread($this->getBoard(), (string) $this->Title->getText(), (string) $this->Content->getText(), $options);
			if ($forum->getEnableAttachments() && $this->Upload->getVisible() && $thread->first_post_id) {
				$post = $forum->getPosts()->findPost((int) $thread->first_post_id);
				foreach ($this->Upload->getFiles() as $item) {
					if ($post !== null && $item->getHasFile()) {
						$forum->getAttachments()->attachUploadedItem($post, $item);
					}
				}
			}
			$this->redirect($this->getUrls()->thread($thread));
		});
	}

	/**
	 * Configures the visible fields.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$forum = $this->getForum();
		$board = $this->getBoard();
		$extra = ['moderators' => $forum->getMembers()->getBoardModeratorUsernames((int) $board->getId())];
		$allowed = $this->can(BEForumPermissions::THREAD_CREATE, $extra) && (!$board->getIsLocked() || $this->getIsModerator((int) $board->getId()));
		if (!$allowed && $this->getErrorMessage() === null) {
			$this->showError($board->getIsLocked() ? $this->t('This board is locked.') : $this->t('You may not create threads in this board.'));
		}
		$this->Form->setVisible($allowed);
		$this->GuestName->setVisible($this->getIsGuest());
		$this->GuestLabel->setVisible($this->getIsGuest());
		$this->Format->setVisible(count($forum->getRenderer()->getFormats()) > 1);
		$this->TagsLabel->setVisible($forum->getEnableTags());
		$this->Tags->setVisible($forum->getEnableTags());
		$this->Upload->setVisible($forum->getEnableAttachments() && $this->can(BEForumPermissions::ATTACH, $extra));
		$this->UploadLabel->setVisible($this->Upload->getVisible());
		$this->PollPanel->setVisible($forum->getEnablePolls() && !$this->getIsGuest() && $this->can(BEForumPermissions::POLL_CREATE, $extra));
		$this->Subscribe->setVisible(!$this->getIsGuest() && $forum->getEnableSubscriptions());
		$this->Cancel->setNavigateUrl($this->getUrls()->board($board));
	}
}
