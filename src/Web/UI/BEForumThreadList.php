<?php

/**
 * BEForumThreadList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumThreadList class.
 *
 * BEForumThreadList renders a paged list of threads: the threads of a board
 * ({@see setBoardID}), of a tag ({@see setTagSlug}) or of a member
 * ({@see setMemberID}).  Pinned threads come first, each row shows flags,
 * tags, author, counters, the last post and an unread marker linking to the
 * first unread post.  A "new thread" button appears when the user may post.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumThreadList BoardID=<%= $this->Request['board'] %> />
 * <com:Belisoful\Forum\Web\UI\BEForumThreadList TagSlug="php" ShowBoard="true" />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\THyperLink $NewThread
 * @property \Prado\Web\UI\WebControls\TRepeater $Threads
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadList extends BEForumControl
{
	use BEForumThreadRowsTrait;

	/** @var null|BEForumPagination the pagination of the last binding */
	private ?BEForumPagination $_pagination = null;

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
	}

	/**
	 * @return string a tag slug listing the threads of a tag
	 */
	public function getTagSlug(): string
	{
		return (string) $this->getViewState('TagSlug', '');
	}

	/**
	 * @param string $slug a tag slug listing the threads of a tag
	 */
	public function setTagSlug($slug): void
	{
		$this->setViewState('TagSlug', TPropertyValue::ensureString($slug), '');
	}

	/**
	 * @return int a member id listing the threads of a member
	 */
	public function getMemberID(): int
	{
		return (int) $this->getViewState('MemberID', 0);
	}

	/**
	 * @param int $id a member id listing the threads of a member
	 */
	public function setMemberID($id): void
	{
		$this->setViewState('MemberID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return string a thread type filter
	 */
	public function getType(): string
	{
		return (string) $this->getViewState('Type', '');
	}

	/**
	 * @param string $type a thread type filter (discussion, question, announcement)
	 */
	public function setType($type): void
	{
		$this->setViewState('Type', strtolower(TPropertyValue::ensureString($type)), '');
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
	 * @return bool whether threads of sub boards are included
	 */
	public function getIncludeSubBoards(): bool
	{
		return (bool) $this->getViewState('IncludeSubBoards', false);
	}

	/**
	 * @param bool $include whether threads of sub boards are included
	 */
	public function setIncludeSubBoards($include): void
	{
		$this->setViewState('IncludeSubBoards', TPropertyValue::ensureBoolean($include), false);
	}

	/**
	 * @return bool whether the board of each thread is shown
	 */
	public function getShowBoard(): bool
	{
		return (bool) $this->getViewState('ShowBoard', false);
	}

	/**
	 * @param bool $show whether the board of each thread is shown
	 */
	public function setShowBoard($show): void
	{
		$this->setViewState('ShowBoard', TPropertyValue::ensureBoolean($show), false);
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
	 * @return bool whether the "new thread" button is shown when allowed
	 */
	public function getShowNewThreadButton(): bool
	{
		return (bool) $this->getViewState('ShowNewThreadButton', true);
	}

	/**
	 * @param bool $show whether the "new thread" button is shown when allowed
	 */
	public function setShowNewThreadButton($show): void
	{
		$this->setViewState('ShowNewThreadButton', TPropertyValue::ensureBoolean($show), true);
	}

	/**
	 * @return null|BEForumPagination the pagination of the last binding
	 */
	public function getPagination(): ?BEForumPagination
	{
		return $this->_pagination;
	}

	/**
	 * Loads the threads of the current mode.
	 * @return array{0: BEForumThread[], 1: BEForumPagination, 2: callable} the threads, the pagination and the page URL callback
	 */
	protected function loadThreads(): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$page = $this->getRequestedPage();
		$pageSize = $this->getPageSize() > 0 ? $this->getPageSize() : null;
		if ($this->getTagSlug() !== '') {
			$tag = $forum->getTags()->findBySlug($this->getTagSlug());
			if ($tag === null) {
				return [[], new BEForumPagination(1, $pageSize ?? $forum->getThreadsPerPage(), 0), fn (int $p) => $urls->tag($this->getTagSlug(), $p)];
			}
			[$threads, $pagination] = $forum->getThreads()->getThreadsByTag($tag, $page, $pageSize);
			return [$threads, $pagination, fn (int $p) => $urls->tag($tag, $p)];
		}
		if ($this->getMemberID() > 0) {
			$member = $forum->getMembers()->findById($this->getMemberID());
			if ($member === null) {
				return [[], new BEForumPagination(1, $pageSize ?? $forum->getItemsPerPage(), 0), fn (int $p) => $urls->index()];
			}
			[$threads, $pagination] = $forum->getThreads()->getThreadsByMember($member, $page, $pageSize);
			return [$threads, $pagination, fn (int $p) => $urls->build($urls->getPagePath('member'), [BEForumUrlBuilder::PARAM_MEMBER => $member->username, BEForumUrlBuilder::PARAM_PAGE => $p > 1 ? $p : null])];
		}
		$board = $forum->getBoards()->getViewableBoard($this->getBoardID());
		$filters = ['include_subboards' => $this->getIncludeSubBoards()];
		if ($this->getType() !== '') {
			$filters['type'] = $this->getType();
		}
		[$threads, $pagination] = $forum->getThreads()->listThreads($board, $page, $filters, $pageSize);
		return [$threads, $pagination, fn (int $p) => $urls->board($board, $p)];
	}

	/**
	 * Loads and binds the threads.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		[$threads, $pagination, $urlCallback] = $this->loadThreads();
		$this->_pagination = $pagination;
		$this->bindRepeater('Threads', $this->buildThreadRows($threads, $this->getShowBoard()));
		$this->Pager->setVisible($this->getShowPager());
		$this->Pager->setPagination($pagination);
		$this->Pager->setUrlCallback($urlCallback);
		$newThread = false;
		$boardId = $this->getBoardID();
		if ($this->getShowNewThreadButton() && $boardId > 0 && $this->getTagSlug() === '' && $this->getMemberID() === 0) {
			$board = $this->getForum()->getBoards()->findBoard($boardId);
			$moderator = $board ? $this->getIsModerator($boardId) : false;
			$newThread = $board !== null && (!$board->getIsLocked() || $moderator)
				&& $this->can(BEForumPermissions::THREAD_CREATE, ['moderators' => $this->getForum()->getMembers()->getBoardModeratorUsernames($boardId)]);
			$this->NewThread->setNavigateUrl($this->getUrls()->newThread($boardId));
		}
		$this->NewThread->setVisible($newThread);
	}
}
