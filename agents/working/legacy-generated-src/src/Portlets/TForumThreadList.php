<?php

/**
 * TForumThreadList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumBoardRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;

/**
 * TForumThreadList renders the paginated list of threads in a board.
 *
 * Pinned/sticky threads are always shown first, followed by regular threads
 * sorted by last-post date descending.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumThreadList extends TForumControl
{
    private $_boardId   = 0;
    private $_page      = 1;

    /** @var TForumBoardRecord|null */
    private $_board = null;

    /** @var TForumThreadRecord[] pinned threads */
    private $_pinned = [];

    /** @var TForumThreadRecord[] normal threads for this page */
    private $_threads = [];

    /** @var int total thread count (for pagination) */
    private $_total = 0;

    /** @var int total pages */
    private $_pageCount = 1;

    public function onLoad($param): void
    {
        parent::onLoad($param);
        if ($this->_boardId > 0) {
            $this->loadThreads();
        }
    }

    private function loadThreads(): void
    {
        $fm = $this->getForumManager();

        $this->_board = TForumBoardRecord::finder()->findByPk($this->_boardId);

        $baseCondition = 'board_id = :bid AND deleted_at IS NULL AND is_approved = 1';
        $params        = [':bid' => $this->_boardId];

        // Pinned threads first (not paginated).
        $this->_pinned = TForumThreadRecord::finder()->with('author')->findAll([
            'condition' => $baseCondition . ' AND (is_pinned = 1 OR is_sticky = 1)',
            'params'    => $params,
            'order'     => 'last_post_at DESC',
        ]) ?: [];

        // Normal threads — paginated.
        $perPage = $fm->getThreadsPerPage();
        $offset  = ($this->_page - 1) * $perPage;

        $this->_total = (int) TForumThreadRecord::finder()->count(
            $baseCondition . ' AND is_pinned = 0 AND is_sticky = 0',
            $params
        );
        $this->_pageCount = max(1, (int) ceil($this->_total / $perPage));

        $this->_threads = TForumThreadRecord::finder()->with('author')->findAll([
            'condition' => $baseCondition . ' AND is_pinned = 0 AND is_sticky = 0',
            'params'    => $params,
            'order'     => 'last_post_at DESC',
            'limit'     => $perPage,
            'offset'    => $offset,
        ]) ?: [];
    }

    // ===================================================================
    // Properties
    // ===================================================================


    public function getBoardId(): int { return $this->_boardId; }
    public function setBoardId(int $v): void { $this->_boardId = $v; }

    public function getPage(): int { return $this->_page; }
    public function setPage(int $v): void { $this->_page = max(1, $v); }

    public function getBoard(): ?TForumBoardRecord { return $this->_board; }
    public function getPinnedThreads(): array { return $this->_pinned; }
    public function getThreads(): array { return $this->_threads; }
    public function getTotal(): int { return $this->_total; }
    public function getPageCount(): int { return $this->_pageCount; }


    public function getThreadUrl(int $threadId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $threadId]);
    }

    public function getNewThreadUrl(): string
    {
        return $this->getForumManager()->createUrl('forum/NewThread', ['board' => $this->_boardId]);
    }

    public function getPageUrl(int $p): string
    {
        return $this->getForumManager()->createUrl('forum/ForumView', ['id' => $this->_boardId, 'p' => $p]);
    }
}
