<?php

/**
 * BEForumBreadcrumbs class file.
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
 * BEForumBreadcrumbs class.
 *
 * BEForumBreadcrumbs renders the navigation trail: forum index, the board
 * ancestors, the board and the thread.  It is driven by {@see setBoardID} /
 * {@see setThreadID} (read from the request when not set) or by records set
 * through {@see setBoard} / {@see setThread}; extra crumbs may be appended
 * with {@see addCrumb}.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumBreadcrumbs ThreadID=<%= $this->Request['thread'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Crumbs
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBreadcrumbs extends BEForumControl
{
	/** @var null|BEForumBoard the board */
	private ?BEForumBoard $_board = null;

	/** @var null|BEForumThread the thread */
	private ?BEForumThread $_thread = null;

	/** @var array<int, array{label: string, url: null|string}> extra crumbs */
	private array $_extra = [];

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
	}

	/**
	 * @return bool whether the forum index crumb is shown
	 */
	public function getShowIndex(): bool
	{
		return (bool) $this->getViewState('ShowIndex', true);
	}

	/**
	 * @param bool $show whether the forum index crumb is shown
	 */
	public function setShowIndex($show): void
	{
		$this->setViewState('ShowIndex', TPropertyValue::ensureBoolean($show), true);
	}

	/**
	 * @param null|BEForumBoard $board the board
	 */
	public function setBoard(?BEForumBoard $board): void
	{
		$this->_board = $board;
	}

	/**
	 * @param null|BEForumThread $thread the thread
	 */
	public function setThread(?BEForumThread $thread): void
	{
		$this->_thread = $thread;
	}

	/**
	 * Appends a crumb after the forum crumbs.
	 * @param string $label the plain text label
	 * @param null|string $url the URL, null for the current page
	 */
	public function addCrumb(string $label, ?string $url = null): void
	{
		$this->_extra[] = ['label' => $label, 'url' => $url];
	}

	/**
	 * Builds the crumbs view model (HTML escaped).
	 * @return array<int, array{label: string, url: string, last: bool}> the crumbs
	 */
	public function getCrumbs(): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$thread = $this->_thread;
		if ($thread === null && $this->getThreadID() > 0) {
			$thread = $forum->getThreads()->findThread($this->getThreadID());
		}
		$board = $this->_board;
		if ($board === null) {
			$boardId = $thread !== null ? (int) $thread->board_id : $this->getBoardID();
			$board = $boardId > 0 ? $forum->getBoards()->findBoard($boardId) : null;
		}
		$crumbs = [];
		if ($this->getShowIndex()) {
			$crumbs[] = ['label' => $forum->getTitle(), 'url' => $urls->index()];
		}
		if ($board !== null) {
			foreach ($forum->getBoards()->getAncestors($board) as $ancestor) {
				$crumbs[] = ['label' => (string) $ancestor->name, 'url' => $urls->board($ancestor)];
			}
			$crumbs[] = ['label' => (string) $board->name, 'url' => $urls->board($board)];
		}
		if ($thread !== null) {
			$crumbs[] = ['label' => (string) $thread->title, 'url' => $urls->thread($thread)];
		}
		foreach ($this->_extra as $extra) {
			$crumbs[] = ['label' => $extra['label'], 'url' => $extra['url']];
		}
		$count = count($crumbs);
		$result = [];
		foreach ($crumbs as $index => $crumb) {
			$result[] = [
				'label' => $this->e($crumb['label']),
				'url' => $this->e((string) ($crumb['url'] ?? '')),
				'last' => $index === $count - 1,
			];
		}
		return $result;
	}

	/**
	 * Binds the crumbs.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$crumbs = $this->getCrumbs();
		$this->setVisible(count($crumbs) > 0);
		$this->bindRepeater('Crumbs', $crumbs);
	}
}
