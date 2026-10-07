<?php

/**
 * BEForumThreadView class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumThreadView class.
 *
 * BEForumThreadView is the complete thread page: breadcrumbs, the thread
 * header (title, flags, tags, author, subscribe button, moderation tools), the
 * poll, the paged post list and the reply editor.  It counts the view, sets
 * the page title, resolves `?post=ID` links to the right page and wires the
 * quote command of posts into the editor.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumThreadView />
 * ```
 * The thread comes from the `thread` request parameter unless
 * {@see setThreadID} is used.
 *
 * @property \Belisoful\Forum\Web\UI\BEForumBreadcrumbs $Crumbs
 * @property \Prado\Web\UI\WebControls\TRepeater $Tags
 * @property \Prado\Web\UI\WebControls\THyperLink $AcceptedLink
 * @property \Belisoful\Forum\Web\UI\BEForumSubscribeButton $Subscribe
 * @property \Belisoful\Forum\Web\UI\BEForumModerationTools $Tools
 * @property \Belisoful\Forum\Web\UI\BEForumPoll $PollView
 * @property \Belisoful\Forum\Web\UI\BEForumPostList $Posts
 * @property \Belisoful\Forum\Web\UI\BEForumPostEditor $Editor
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadView extends BEForumControl
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
	 * @return bool whether the breadcrumbs are shown
	 */
	public function getShowBreadcrumbs(): bool
	{
		return (bool) $this->getViewState('ShowBreadcrumbs', true);
	}

	/**
	 * @param bool $show whether the breadcrumbs are shown
	 */
	public function setShowBreadcrumbs($show): void
	{
		$this->setViewState('ShowBreadcrumbs', TPropertyValue::ensureBoolean($show), true);
	}

	/**
	 * @return BEForumThread the thread
	 */
	public function getThread(): BEForumThread
	{
		if ($this->_thread === null) {
			$this->_thread = $this->getForum()->getThreads()->getThread($this->getThreadID());
		}
		return $this->_thread;
	}

	/**
	 * Resolves `?post=ID` requests and distributes the thread to the children.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		if ($this->getThreadID() === 0) {
			$postId = $this->getRequestInt(BEForumUrlBuilder::PARAM_POST, 0);
			if ($postId > 0) {
				$post = $this->getForum()->getPosts()->getPost($postId);
				$this->redirect($this->getUrls()->post($post));
				return;
			}
		}
		$thread = $this->getThread();
		$this->Posts->setThread($thread);
		$this->Editor->setThreadID((int) $thread->getId());
		$this->Tools->setThreadID((int) $thread->getId());
		$this->PollView->setThreadID((int) $thread->getId());
		$this->Subscribe->setTargetType(BEForumSubscription::TYPE_THREAD);
		$this->Subscribe->setTargetID((int) $thread->getId());
		$this->Crumbs->setThread($thread);
	}

	/**
	 * Counts the view on the first display.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		if (!$this->getPage()->getIsPostBack()) {
			$this->getForum()->getThreads()->countView($this->getThread());
		}
	}

	/**
	 * Handles post commands: quote fills the editor.
	 * @param mixed $sender the post list
	 * @param BEForumCommandEventParameter $param the command
	 */
	public function postCommand($sender, $param): void
	{
		if ($param->getName() === 'quote') {
			$post = $this->getForum()->getPosts()->findPost($param->getPostID());
			if ($post !== null) {
				$this->Editor->quote($post);
			}
		}
	}

	/**
	 * @return string the flags and type HTML of the header
	 */
	public function getFlagsHtml(): string
	{
		$thread = $this->getThread();
		$html = '';
		if ($thread->getIsPinned()) {
			$html .= $this->flag('pinned', '&#128204;', $this->t('Pinned'));
		}
		if ($thread->getIsLocked()) {
			$html .= $this->flag('locked', '&#128274;', $this->t('Locked'));
		}
		if ($thread->getIsSolved()) {
			$html .= $this->flag('solved', '&#10004;', $this->t('Solved'));
		}
		if (!$thread->getIsApproved()) {
			$html .= $this->flag('pending', '&#9203;', $this->t('Awaiting approval'));
		}
		if ($thread->getIsDeleted()) {
			$html .= $this->flag('deleted', '&#128465;', $this->t('Deleted'));
		}
		$label = match ($thread->type) {
			BEForumThread::TYPE_QUESTION => $this->t('Question'),
			BEForumThread::TYPE_ANNOUNCEMENT => $this->t('Announcement'),
			default => '',
		};
		if ($label !== '') {
			$html .= '<span class="' . $this->css('type', (string) $thread->type) . '">' . $this->e($label) . '</span>';
		}
		return $html;
	}

	/**
	 * @return array<int, array{name: string, url: string}> the tag rows
	 */
	public function getTagRows(): array
	{
		if (!$this->getForum()->getEnableTags()) {
			return [];
		}
		$rows = [];
		foreach ($this->getForum()->getTags()->getThreadTags($this->getThread()) as $tag) {
			$rows[] = ['name' => $this->e((string) $tag->name), 'url' => $this->e($this->getUrls()->tag($tag))];
		}
		return $rows;
	}

	/**
	 * @return string the author line HTML
	 */
	public function getAuthorHtml(): string
	{
		$thread = $this->getThread();
		$author = $thread->member_id ? $this->getForum()->getMembers()->findById((int) $thread->member_id) : null;
		return $this->th('Started by {0} {1}', [$this->memberLink($author, $thread->guest_name), $this->timeTag($thread->created_at)]);
	}

	/**
	 * Binds the header.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$thread = $this->getThread();
		$this->setPageTitle((string) $thread->title);
		$this->Crumbs->setVisible($this->getShowBreadcrumbs());
		$this->bindRepeater('Tags', $this->getTagRows());
		$this->Editor->setVisible($this->getForum()->getThreads()->canReply($thread));
		$this->AcceptedLink->setVisible($thread->getIsSolved());
		if ($thread->getIsSolved()) {
			$accepted = $this->getForum()->getPosts()->findPost((int) $thread->accepted_post_id);
			$this->AcceptedLink->setNavigateUrl($accepted ? $this->getUrls()->post($accepted) : '');
		}
	}
}
