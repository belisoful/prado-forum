<?php

/**
 * BEForumPostEditor class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Content\BEForumContentRenderer;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumPostEditor class.
 *
 * BEForumPostEditor is the reply form of a thread ({@see setThreadID}) and
 * the edit form of a post ({@see setPostID}).  It offers a Markdown/plain text
 * editor with preview, an optional reply target set by {@see quote}, guest
 * name for anonymous posting, file attachments, an edit reason in edit mode
 * and a subscribe checkbox.  On success the browser is redirected to the post.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumPostEditor ThreadID=<%= $this->Request['thread'] %> />
 * <com:Belisoful\Forum\Web\UI\BEForumPostEditor PostID=<%= $this->Request['post'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TPanel $Form
 * @property \Prado\Web\UI\WebControls\TLabel $ReplyTo
 * @property \Prado\Web\UI\WebControls\TLinkButton $ClearReplyTo
 * @property \Prado\Web\UI\WebControls\TLabel $GuestLabel
 * @property \Prado\Web\UI\WebControls\TTextBox $GuestName
 * @property \Prado\Web\UI\WebControls\TTextBox $Content
 * @property \Prado\Web\UI\WebControls\TDropDownList $Format
 * @property \Prado\Web\UI\WebControls\TLabel $UploadLabel
 * @property \Prado\Web\UI\WebControls\TFileUpload $Upload
 * @property \Prado\Web\UI\WebControls\TLabel $ReasonLabel
 * @property \Prado\Web\UI\WebControls\TTextBox $Reason
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
class BEForumPostEditor extends BEForumControl
{
	/** @var bool whether the editor is usable by the current user */
	private bool $_available = true;

	/**
	 * @return int the thread id (reply mode), read from the `thread` request parameter when unset
	 */
	public function getThreadID(): int
	{
		$id = (int) $this->getViewState('ThreadID', 0);
		if ($id > 0) {
			return $id;
		}
		return $this->getPostID() > 0 ? 0 : $this->getRequestInt(BEForumUrlBuilder::PARAM_THREAD, 0);
	}

	/**
	 * @param int $id the thread id (reply mode)
	 */
	public function setThreadID($id): void
	{
		$this->setViewState('ThreadID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return int the post id (edit mode), read from the `post` request parameter when neither a thread nor a post is set and the request names no thread
	 */
	public function getPostID(): int
	{
		$id = (int) $this->getViewState('PostID', 0);
		if ($id > 0) {
			return $id;
		}
		if ((int) $this->getViewState('ThreadID', 0) > 0 || $this->getRequestInt(BEForumUrlBuilder::PARAM_THREAD, 0) > 0) {
			return 0;
		}
		return $this->getRequestInt(BEForumUrlBuilder::PARAM_POST, 0);
	}

	/**
	 * @param int $id the post id (edit mode)
	 */
	public function setPostID($id): void
	{
		$this->setViewState('PostID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return int the post being replied to
	 */
	public function getReplyToID(): int
	{
		return (int) $this->getViewState('ReplyToID', 0);
	}

	/**
	 * @param int $id the post being replied to
	 */
	public function setReplyToID($id): void
	{
		$this->setViewState('ReplyToID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return bool whether the editor edits an existing post
	 */
	public function getIsEditMode(): bool
	{
		return $this->getPostID() > 0;
	}

	/**
	 * @return string the submit button text
	 */
	public function getSubmitText(): string
	{
		return (string) $this->getViewState('SubmitText', '');
	}

	/**
	 * @param string $text the submit button text
	 */
	public function setSubmitText($text): void
	{
		$this->setViewState('SubmitText', TPropertyValue::ensureString($text), '');
	}

	/**
	 * Prefills the editor with a quote of a post and sets it as reply target.
	 * @param BEForumPost $post the post
	 */
	public function quote(BEForumPost $post): void
	{
		$this->ensureChildControls();
		$this->setReplyToID((int) $post->getId());
		$lines = preg_split('/\R/', trim((string) $post->content)) ?: [];
		$quoted = '> ' . implode("\n> ", $lines);
		$existing = trim((string) $this->Content->getText());
		$this->Content->setText(($existing !== '' ? $existing . "\n\n" : '') . $this->t('{0} wrote:', [$post->getAuthorName()]) . "\n" . $quoted . "\n\n");
		$this->Content->focus();
	}

	/**
	 * @return string[] the selectable content formats
	 */
	public function getFormats(): array
	{
		return $this->getForum()->getRenderer()->getFormats();
	}

	/**
	 * Loads the post in edit mode and configures the fields.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$forum = $this->getForum();
		if (!$this->getPage()->getIsPostBack()) {
			$formats = [];
			foreach ($this->getFormats() as $format) {
				$formats[$format] = $this->e(ucfirst($format));
			}
			$this->Format->setDataSource($formats);
			$this->Format->dataBind();
			$this->Format->setSelectedValue($forum->getContentFormat());
			$this->Subscribe->setChecked(true);
			if ($this->getIsEditMode()) {
				try {
					$post = $forum->getPosts()->getPost($this->getPostID());
					if (!$forum->getPosts()->canEdit($post)) {
						throw new BEForumForbiddenException(BEForumPermissions::POST_EDIT, 'forum_edit_window_closed');
					}
					$this->Content->setText((string) $post->content);
					if (in_array((string) $post->content_format, $this->getFormats(), true)) {
						$this->Format->setSelectedValue((string) $post->content_format);
					}
				} catch (BEForumForbiddenException | BEForumNotFoundException $e) {
					$this->_available = false;
					$this->showError($e->getMessage());
				}
			}
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
	 * Saves the reply or the edit.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function submitClicked($sender, $param): void
	{
		if (!$this->getPage()->getIsValid()) {
			return;
		}
		$forum = $this->getForum();
		$content = (string) $this->Content->getText();
		$format = (string) $this->Format->getSelectedValue();
		$this->attempt(function () use ($forum, $content, $format): void {
			if ($this->getIsEditMode()) {
				$post = $forum->getPosts()->getPost($this->getPostID());
				$post = $forum->getPosts()->updatePost($post, $content, (string) $this->Reason->getText(), $format);
			} else {
				$thread = $forum->getThreads()->getThread($this->getThreadID());
				$post = $forum->getPosts()->createPost($thread, $content, [
					'reply_to' => $this->getReplyToID() > 0 ? $this->getReplyToID() : null,
					'guest_name' => (string) $this->GuestName->getText(),
					'format' => $format,
					'subscribe' => $this->Subscribe->getChecked(),
				]);
			}
			$this->storeAttachments($post);
			$this->redirect($this->getUrls()->post($post));
		});
	}

	/**
	 * Stores the uploaded files of the form as attachments of a post.
	 * @param BEForumPost $post the post
	 */
	protected function storeAttachments(BEForumPost $post): void
	{
		$forum = $this->getForum();
		if (!$forum->getEnableAttachments() || !$this->Upload->getVisible()) {
			return;
		}
		foreach ($this->Upload->getFiles() as $item) {
			if ($item->getHasFile()) {
				$forum->getAttachments()->attachUploadedItem($post, $item);
			}
		}
	}

	/**
	 * Configures the visible fields.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$forum = $this->getForum();
		$thread = null;
		$boardId = 0;
		if (!$this->getIsEditMode() && $this->getThreadID() > 0) {
			$thread = $forum->getThreads()->findThread($this->getThreadID());
			$boardId = $thread ? (int) $thread->board_id : 0;
			if ($thread === null || !$forum->getThreads()->canReply($thread)) {
				$this->_available = false;
				if ($thread !== null && $thread->getIsLocked()) {
					$this->showError($this->t('This thread is locked.'));
				}
			}
		} elseif ($this->getIsEditMode()) {
			$post = $forum->getPosts()->findPost($this->getPostID());
			$boardId = $post ? (int) $post->board_id : 0;
		}
		$this->Form->setVisible($this->_available);
		$this->GuestName->setVisible($this->getIsGuest() && !$this->getIsEditMode());
		$this->GuestLabel->setVisible($this->GuestName->getVisible());
		$this->Format->setVisible(count($this->getFormats()) > 1);
		$this->Upload->setVisible($forum->getEnableAttachments() && $this->can(BEForumPermissions::ATTACH, ['moderators' => $forum->getMembers()->getBoardModeratorUsernames($boardId)]));
		$this->UploadLabel->setVisible($this->Upload->getVisible());
		$this->Subscribe->setVisible(!$this->getIsEditMode() && !$this->getIsGuest() && $forum->getEnableSubscriptions());
		$this->Reason->setVisible($this->getIsEditMode());
		$this->ReasonLabel->setVisible($this->getIsEditMode());
		$this->Submit->setText($this->e($this->getSubmitText() !== '' ? $this->getSubmitText() : $this->t($this->getIsEditMode() ? 'Save changes' : 'Post reply')));
		$replyTo = $this->getReplyToID() > 0 ? $forum->getPosts()->findPost($this->getReplyToID()) : null;
		$this->ReplyTo->setVisible($replyTo !== null);
		$this->ClearReplyTo->setVisible($replyTo !== null);
		$this->ReplyTo->setText($replyTo ? $this->th('Replying to {0}', [$this->e($replyTo->getAuthorName())]) : '');
		$this->Cancel->setNavigateUrl($thread ? $this->getUrls()->thread($thread) : ($this->getIsEditMode() && isset($post) && $post ? $this->getUrls()->post($post) : $this->getUrls()->index()));
	}

	/**
	 * Clears the reply target.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function clearReplyToClicked($sender, $param): void
	{
		$this->setReplyToID(0);
	}
}
