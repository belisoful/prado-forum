<?php

/**
 * BEForumPostView class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Managers\BEForumAttachmentManager;
use Belisoful\Forum\Security\BEForumPermissions;

/**
 * BEForumPostView class.
 *
 * BEForumPostView renders one post: the author card, the content, edit
 * information, attachments, reactions and the actions the current user may
 * perform (quote, edit, delete/restore, approve, accept as answer, bookmark,
 * report).  It is the item renderer of {@see BEForumPostList} and also of the
 * search result and bookmark lists.  The row it receives is built by
 * {@see BEForumPostRowsTrait::buildPostRows}; on a postback only the `PostID` (kept in
 * view state) is available and the record is loaded again.
 *
 * Commands that need the thread page (quote) bubble up as
 * `TCommandEventParameter` to the repeater; everything else is handled here
 * and the page is reloaded afterwards.
 *
 * @property \Belisoful\Forum\Web\UI\BEForumMemberCard $Author
 * @property \Prado\Web\UI\WebControls\TRepeater $Attachments
 * @property \Belisoful\Forum\Web\UI\BEForumReactionBar $Reactions
 * @property \Prado\Web\UI\WebControls\TLinkButton $Quote
 * @property \Prado\Web\UI\WebControls\THyperLink $Edit
 * @property \Prado\Web\UI\WebControls\TLinkButton $Delete
 * @property \Prado\Web\UI\WebControls\TLinkButton $Restore
 * @property \Prado\Web\UI\WebControls\TLinkButton $Approve
 * @property \Prado\Web\UI\WebControls\TLinkButton $Accept
 * @property \Prado\Web\UI\WebControls\TLinkButton $Unaccept
 * @property \Prado\Web\UI\WebControls\TLinkButton $Bookmark
 * @property \Prado\Web\UI\WebControls\TLinkButton $Report
 * @property \Prado\Web\UI\WebControls\TPanel $ReportPanel
 * @property \Prado\Web\UI\WebControls\TTextBox $ReportReason
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPostView extends BEForumItemRenderer
{
	/** @var null|BEForumPost the post */
	private ?BEForumPost $_post = null;

	/** @var null|string the last error */
	private ?string $_error = null;

	/**
	 * @return int the post id
	 */
	public function getPostID(): int
	{
		return (int) $this->getViewState('PostID', 0);
	}

	/**
	 * @param int $id the post id
	 */
	public function setPostID($id): void
	{
		$this->setViewState('PostID', (int) $id, 0);
	}

	/**
	 * @return null|BEForumPost the post of this view
	 */
	public function getPost(): ?BEForumPost
	{
		if ($this->_post === null && $this->getPostID() > 0) {
			$this->_post = $this->getForum()->getPosts()->findPost($this->getPostID());
		}
		return $this->_post;
	}

	/**
	 * Stores the row keys needed after a postback and configures the children.
	 * @param mixed $param the event parameter
	 */
	public function onDataBinding($param)
	{
		parent::onDataBinding($param);
		$post = $this->item('post');
		if ($post instanceof BEForumPost) {
			$this->_post = $post;
			$this->setPostID((int) $post->getId());
			$author = $this->item('author');
			$this->Author->setMemberRecord($author instanceof BEForumMember ? $author : null);
			$this->Author->setGuestName($post->guest_name);
			$this->Reactions->setPostID((int) $post->getId());
			$this->Reactions->setAuthorID((int) $post->member_id);
			$this->Reactions->setSummary($this->item('reactions', []));
			$this->Reactions->setMemberReaction((string) $this->item('memberReaction', ''));
		}
	}

	/**
	 * Binds the attachments and restores the transient row state (open report
	 * form, error message) kept by the owning list across the rebind.
	 */
	protected function bindChildRepeaters(): void
	{
		$this->bindChildRepeater('Attachments', 'attachments');
		$this->ReportPanel->setVisible($this->is('reportOpen'));
		$error = (string) $this->item('error', '');
		if ($error !== '') {
			$this->_error = html_entity_decode($error, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$this->Error->setText($error);
			$this->Error->setVisible(true);
		}
	}

	/**
	 * @return null|object the nearest ancestor using {@see BEForumPostRowsTrait}, which keeps the per post state across rebinding
	 */
	protected function getRowsOwner(): ?object
	{
		for ($control = $this->getParent(); $control !== null; $control = $control->getParent()) {
			if (method_exists($control, 'setPostError')) {
				return $control;
			}
		}
		return null;
	}

	/**
	 * @return string the wrapper classes
	 */
	public function getRowCss(): string
	{
		$classes = [$this->css('post')];
		foreach (['deleted', 'pending', 'accepted', 'first'] as $modifier) {
			if ($this->is($modifier)) {
				$classes[] = $this->css('post', $modifier);
			}
		}
		return implode(' ', $classes);
	}

	/**
	 * @return string the status line HTML (deleted, pending, edited)
	 */
	public function getStatusHtml(): string
	{
		$html = '';
		if ($this->is('deleted')) {
			$html .= '<span class="' . $this->css('post-status', 'deleted') . '">' . $this->te('This post has been deleted.') . '</span>';
		}
		if ($this->is('pending')) {
			$html .= '<span class="' . $this->css('post-status', 'pending') . '">' . $this->te('Awaiting moderator approval.') . '</span>';
		}
		if ($this->is('accepted')) {
			$html .= '<span class="' . $this->css('post-status', 'accepted') . '">&#10004; ' . $this->te('Accepted answer') . '</span>';
		}
		if ($this->item('edited', '') !== '') {
			$html .= '<span class="' . $this->css('post-status', 'edited') . '">' . $this->item('edited') . '</span>';
		}
		return $html;
	}

	/**
	 * @return string the "in reply to" HTML
	 */
	public function getReplyToHtml(): string
	{
		if ($this->item('replyToUrl', '') === '') {
			return '';
		}
		return '<a class="' . $this->css('post-reply-to') . '" href="' . $this->item('replyToUrl') . '">' . $this->th('In reply to {0}', [$this->item('replyToAuthor')]) . '</a>';
	}

	/**
	 * @return string the thread context HTML (for lists spanning threads)
	 */
	public function getContextHtml(): string
	{
		if ($this->item('threadTitle', '') === '') {
			return '';
		}
		return '<a class="' . $this->css('post-context') . '" href="' . $this->item('threadUrl') . '">' . $this->item('threadTitle') . '</a>';
	}

	/**
	 * Configures the action buttons for the current user.
	 */
	protected function configureActions(): void
	{
		$post = $this->getPost();
		$forum = $this->getForum();
		if ($post === null) {
			foreach (['Quote', 'Edit', 'Delete', 'Restore', 'Approve', 'Accept', 'Unaccept', 'Bookmark', 'Report'] as $id) {
				$this->$id->setVisible(false);
			}
			return;
		}
		$posts = $forum->getPosts();
		$thread = $forum->getThreads()->findThread((int) $post->thread_id);
		$moderator = $this->getIsModerator((int) $post->board_id);
		$deleted = $post->getIsDeleted();
		$canReply = $thread !== null && $forum->getThreads()->canReply($thread) && !$deleted && $this->is('showQuote');
		$this->Quote->setVisible($canReply);
		$this->Edit->setVisible(!$deleted && $posts->canEdit($post));
		$this->Edit->setNavigateUrl($this->getUrls()->editPost($post));
		$this->Delete->setVisible(!$deleted && $posts->canDelete($post));
		$this->Restore->setVisible($deleted && $moderator);
		$this->Approve->setVisible(!$post->getIsApproved() && !$deleted && $moderator);
		$canAccept = $thread !== null && $thread->getIsQuestion() && !$post->getIsFirstPost() && !$deleted && $forum->getThreads()->canEdit($thread);
		$accepted = $thread !== null && (int) $thread->accepted_post_id === (int) $post->getId();
		$this->Accept->setVisible($canAccept && !$accepted);
		$this->Unaccept->setVisible($canAccept && $accepted);
		$bookmarked = $this->is('bookmarked');
		$this->Bookmark->setVisible(!$deleted && $forum->getEnableBookmarks() && !$this->getIsGuest() && $this->can(BEForumPermissions::BOOKMARK));
		$this->Bookmark->setText($this->te($bookmarked ? 'Remove bookmark' : 'Bookmark'));
		$this->Report->setVisible(!$deleted && !$this->getIsGuest() && !$this->getIsModerator((int) $post->board_id) && $this->can(BEForumPermissions::REPORT));
	}

	/**
	 * Configures the actions before rendering.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->configureActions();
	}

	/**
	 * Runs an action, showing forum errors inline, and reloads the page on success.
	 * @param callable $action the action
	 */
	protected function perform(callable $action): void
	{
		try {
			$action();
			$this->getResponse()->reload();
		} catch (BEForumValidationException | BEForumForbiddenException | BEForumNotFoundException $e) {
			$this->_error = $e->getMessage();
			$this->Error->setText($this->e($e->getMessage()));
			$this->Error->setVisible(true);
			$this->getRowsOwner()?->setPostError($this->getPostID(), $e->getMessage());
		}
	}

	/**
	 * @return null|string the last error
	 */
	public function getErrorMessage(): ?string
	{
		return $this->_error;
	}

	/**
	 * Deletes the post.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function deleteClicked($sender, $param): void
	{
		$this->perform(fn () => $this->getForum()->getPosts()->deletePost($this->getForum()->getPosts()->getPost($this->getPostID())));
	}

	/**
	 * Restores the post.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function restoreClicked($sender, $param): void
	{
		$this->perform(function (): void {
			$post = $this->getForum()->getPosts()->findPost($this->getPostID());
			if ($post !== null) {
				$this->getForum()->getPosts()->restorePost($post);
			}
		});
	}

	/**
	 * Approves the post.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function approveClicked($sender, $param): void
	{
		$this->perform(function (): void {
			$post = $this->getForum()->getPosts()->findPost($this->getPostID());
			if ($post !== null) {
				$this->getForum()->getPosts()->approvePost($post);
			}
		});
	}

	/**
	 * Marks the post as the accepted answer.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function acceptClicked($sender, $param): void
	{
		$this->perform(function (): void {
			$post = $this->getForum()->getPosts()->getPost($this->getPostID());
			$thread = $this->getForum()->getThreads()->getThread((int) $post->thread_id);
			$this->getForum()->getThreads()->setAcceptedPost($thread, $post);
		});
	}

	/**
	 * Clears the accepted answer.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function unacceptClicked($sender, $param): void
	{
		$this->perform(function (): void {
			$post = $this->getForum()->getPosts()->getPost($this->getPostID());
			$thread = $this->getForum()->getThreads()->getThread((int) $post->thread_id);
			$this->getForum()->getThreads()->setAcceptedPost($thread, null);
		});
	}

	/**
	 * Toggles the bookmark.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function bookmarkClicked($sender, $param): void
	{
		$this->perform(fn () => $this->getForum()->getBookmarks()->toggle($this->getForum()->getPosts()->getPost($this->getPostID())));
	}

	/**
	 * Shows the report form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function reportClicked($sender, $param): void
	{
		$this->ReportPanel->setVisible(true);
		$this->getRowsOwner()?->openReportFor($this->getPostID());
	}

	/**
	 * Sends the report.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function sendReportClicked($sender, $param): void
	{
		$reason = (string) $this->ReportReason->getText();
		$this->ReportPanel->setVisible(true);
		$this->getRowsOwner()?->openReportFor($this->getPostID());
		$this->perform(fn () => $this->getForum()->getModeration()->report($this->getForum()->getPosts()->getPost($this->getPostID()), $reason));
	}

	/**
	 * Hides the report form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function cancelReportClicked($sender, $param): void
	{
		$this->ReportPanel->setVisible(false);
		$this->getRowsOwner()?->openReportFor(0);
	}
}
