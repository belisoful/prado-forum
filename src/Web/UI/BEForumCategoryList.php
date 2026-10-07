<?php

/**
 * BEForumCategoryList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumCategory;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Prado\TPropertyValue;
use Prado\Web\UI\WebControls\TRepeater;
use Prado\Web\UI\WebControls\TRepeaterItem;
use Prado\Web\UI\WebControls\TRepeaterItemEventParameter;

/**
 * BEForumCategoryList class.
 *
 * BEForumCategoryList is the forum index: every visible category with its
 * top level boards, their sub boards, descriptions, counters, last post and
 * unread markers.  Set {@see setCategoryID} to show a single category.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumCategoryList />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Categories
 * @property \Prado\Web\UI\WebControls\TRepeater $Boards
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumCategoryList extends BEForumControl
{
	/**
	 * @return int a single category to show, 0 for all
	 */
	public function getCategoryID(): int
	{
		return (int) $this->getViewState('CategoryID', 0);
	}

	/**
	 * @param int $id a single category to show, 0 for all
	 */
	public function setCategoryID($id): void
	{
		$this->setViewState('CategoryID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return bool whether board descriptions are shown
	 */
	public function getShowDescriptions(): bool
	{
		return (bool) $this->getViewState('ShowDescriptions', true);
	}

	/**
	 * @param bool $show whether board descriptions are shown
	 */
	public function setShowDescriptions($show): void
	{
		$this->setViewState('ShowDescriptions', TPropertyValue::ensureBoolean($show), true);
	}

	/**
	 * Builds the view model (HTML escaped).
	 * @return array<int, array{id: int, name: string, description: string, url: string, boards: array}> the categories
	 */
	public function getCategoriesData(): array
	{
		$forum = $this->getForum();
		$boards = $forum->getBoards();
		$categories = $boards->getVisibleCategories();
		if ($this->getCategoryID() > 0) {
			$categories = array_filter($categories, fn (BEForumCategory $category) => $category->getId() === $this->getCategoryID());
		}
		$visibleBoards = $boards->getVisibleBoards();
		$threadIds = [];
		$memberIds = [];
		foreach ($visibleBoards as $board) {
			if ($board->last_thread_id) {
				$threadIds[] = (int) $board->last_thread_id;
			}
			if ($board->last_poster_member_id) {
				$memberIds[] = (int) $board->last_poster_member_id;
			}
		}
		$threads = $forum->getThreads()->getThreadsByIds($threadIds);
		$members = $forum->getMembers()->getMembersByIds($memberIds);
		$unread = $forum->getReadTracker()->getBoardUnreadMap($visibleBoards);
		$result = [];
		foreach ($categories as $category) {
			$rows = [];
			foreach ($boards->getCategoryBoards($category) as $board) {
				$rows[] = $this->boardRow($board, $threads, $members, $unread);
			}
			if (!$rows && $this->getCategoryID() === 0) {
				continue;
			}
			$result[] = [
				'id' => (int) $category->getId(),
				'name' => $this->e((string) $category->name),
				'description' => $category->description ? $forum->renderContent((string) $category->description) : '',
				'boards' => $rows,
			];
		}
		return $result;
	}

	/**
	 * Builds the view model of one board.
	 * @param BEForumBoard $board the board
	 * @param array $threads the last threads keyed by id
	 * @param array $members the last posters keyed by id
	 * @param array $unread the unread map
	 * @return array the row
	 */
	protected function boardRow(BEForumBoard $board, array $threads, array $members, array $unread): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$lastThread = $board->last_thread_id ? ($threads[(int) $board->last_thread_id] ?? null) : null;
		$lastPoster = $board->last_poster_member_id ? ($members[(int) $board->last_poster_member_id] ?? null) : null;
		$subBoards = [];
		foreach ($forum->getBoards()->getSubBoards($board) as $sub) {
			$subBoards[] = ['name' => $this->e((string) $sub->name), 'url' => $this->e($urls->board($sub)), 'unread' => $unread[(int) $sub->getId()] ?? false];
		}
		return [
			'id' => (int) $board->getId(),
			'name' => $this->e((string) $board->name),
			'url' => $this->e($urls->board($board)),
			'description' => $this->getShowDescriptions() && $board->description ? $forum->renderContent((string) $board->description) : '',
			'locked' => $board->getIsLocked(),
			'private' => $board->getIsPrivate(),
			'unread' => $unread[(int) $board->getId()] ?? false,
			'threads' => (int) $board->thread_count,
			'posts' => (int) $board->post_count,
			'lastTitle' => $lastThread ? $this->e((string) $lastThread->title) : '',
			'lastUrl' => $lastThread ? $this->e($urls->thread($lastThread, $forum->getPosts()->getPageOfPost($this->lastPostOf($lastThread)) ?: 1, $board->last_post_id ? (int) $board->last_post_id : null)) : '',
			'lastPoster' => $lastPoster ? $this->memberLink($lastPoster) : '',
			'lastTime' => $this->timeTag($board->last_post_at),
			'subBoards' => $subBoards,
			'modifier' => $unread[(int) $board->getId()] ?? false ? 'unread' : ($board->getIsLocked() ? 'locked' : null),
		];
	}

	/**
	 * @param BEForumThread $thread a thread
	 * @return BEForumPost a stand-in post carrying the thread's last position for page calculation
	 */
	protected function lastPostOf(BEForumThread $thread): BEForumPost
	{
		$post = new BEForumPost();
		$post->thread_id = $thread->getId();
		$post->position = $thread->getPostCount();
		return $post;
	}

	/**
	 * Binds the categories.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->bindRepeater('Categories', $this->getCategoriesData());
	}

	/**
	 * Binds the boards of a category item.
	 * @param mixed $sender the repeater
	 * @param TRepeaterItemEventParameter $param the event parameter
	 */
	public function categoryDataBound($sender, $param): void
	{
		$item = $param->getItem();
		$boards = $item->findControl('Boards');
		$data = $item instanceof TRepeaterItem ? $item->getData() : null;
		if (is_array($data) && $boards instanceof TRepeater) {
			$boards->setDataSource($data['boards']);
			$boards->dataBind();
		}
	}
}
