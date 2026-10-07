<?php

/**
 * BEForumPostList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;

/**
 * BEForumPostList class.
 *
 * BEForumPostList renders one page of the posts of a thread with
 * {@see BEForumPostView} items and a pager, marks the thread read up to the
 * last shown post and forwards post commands (quote) to its container through
 * the `OnPostCommand` event.  {@see BEForumPostRowsTrait::buildPostRows} is also used by the search
 * results and bookmark lists to render posts from other threads.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumPostList ThreadID=<%= $this->Request['thread'] %> />
 * ```
 *
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 * @property \Prado\Web\UI\WebControls\TRepeater $Posts
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPostList extends BEForumControl
{
	use BEForumPostRowsTrait;

	/** @var null|BEForumThread the thread */
	private ?BEForumThread $_thread = null;

	/** @var null|BEForumPagination the pagination of the last binding */
	private ?BEForumPagination $_pagination = null;

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
	 * @return int the page size, 0 for the module default
	 */
	public function getPageSize(): int
	{
		return (int) $this->getViewState('PageSize', 0);
	}

	/**
	 * @param int $size the page size, 0 for the module default
	 */
	public function setPageSize($size): void
	{
		$this->setViewState('PageSize', max(0, TPropertyValue::ensureInteger($size)), 0);
	}

	/**
	 * @return bool whether the pager is shown
	 */
	public function getShowPager(): bool
	{
		return (bool) $this->getViewState('ShowPager', true);
	}

	/**
	 * @param bool $show whether the pager is shown
	 */
	public function setShowPager($show): void
	{
		$this->setViewState('ShowPager', TPropertyValue::ensureBoolean($show), true);
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
	 * @param BEForumThread $thread the thread
	 */
	public function setThread(BEForumThread $thread): void
	{
		$this->_thread = $thread;
		$this->setViewState('ThreadID', (int) $thread->getId(), 0);
	}

	/**
	 * @return null|BEForumPagination the pagination of the last binding
	 */
	public function getPagination(): ?BEForumPagination
	{
		return $this->_pagination;
	}

	/**
	 * Forwards post commands to the container.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function itemCommand($sender, $param): void
	{
		$this->onPostCommand(new BEForumCommandEventParameter((string) $param->getCommandName(), (int) $param->getCommandParameter()));
	}

	/**
	 * Raised when a post command (quote) bubbles out of a post view.
	 * @param BEForumCommandEventParameter $param the command
	 */
	public function onPostCommand($param)
	{
		$this->raiseEvent('OnPostCommand', $this, $param);
	}

	/**
	 * Loads and binds the posts.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$thread = $this->getThread();
		$page = $this->getRequestedPage();
		[$posts, $pagination] = $this->getForum()->getPosts()->listPosts($thread, $page, $this->getPageSize() > 0 ? $this->getPageSize() : null);
		$this->_pagination = $pagination;
		$this->bindRepeater('Posts', $this->buildPostRows($posts));
		$this->Pager->setVisible($this->getShowPager());
		$this->Pager->setPagination($pagination);
		$this->Pager->setUrlCallback(fn (int $p) => $this->getUrls()->thread($thread, $p));
		if ($posts && !$this->getIsGuest()) {
			$last = max(array_map(fn (BEForumPost $post) => (int) $post->getId(), $posts));
			$this->getForum()->getReadTracker()->markThreadRead($thread, $last);
		}
	}
}
