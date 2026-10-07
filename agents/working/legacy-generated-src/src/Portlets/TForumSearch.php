<?php

/**
 * TForumSearch class file.
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

/**
 * TForumSearch renders the forum search form and, when results are
 * available, displays them inline.
 *
 * Set `ShowResults="true"` on the Search page to display results in the
 * portlet itself; otherwise the form submits to the Search page.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumSearch extends TForumControl
{
    private $_showResults = false;
    private $_results     = [];
    private $_total       = 0;
    private $_pageCount   = 1;
    private $_page        = 1;
    private $_query       = '';
    private $_searchIn    = 'threads'; // 'threads' | 'posts'
    private $_boardId     = 0;

    /** @var TForumBoardRecord[] */
    private $_boards = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);

        $request = $this->getRequest();
        $this->_query    = trim($request->getParam('q', ''));
        $this->_searchIn = $request->getParam('in', 'threads');
        $this->_boardId  = (int) $request->getParam('board', 0);
        $this->_page     = max(1, (int) $request->getParam('p', 1));

        $this->_boards = TForumBoardRecord::finder()->findAll(
            ['condition' => 'is_active = 1', 'order' => 'sort_order ASC']
        ) ?: [];

        if ($this->_showResults && $this->_query !== '') {
            $this->runSearch();
        }
    }

    private function runSearch(): void
    {
        $svc = $this->getForumManager()->getSearchService();

        if ($this->_searchIn === 'posts') {
            $result = $svc->searchPosts($this->_query, $this->_boardId, $this->_page);
        } else {
            $result = $svc->searchThreads($this->_query, $this->_boardId, $this->_page);
        }

        $this->_results   = $result['results'];
        $this->_total     = $result['total'];
        $this->_pageCount = $result['pages'];
    }

    // ===================================================================
    // Properties
    // ===================================================================


    public function getShowResults(): bool { return $this->_showResults; }
    public function setShowResults(bool $v): void { $this->_showResults = $v; }

    public function getQuery(): string { return $this->_query; }
    public function getSearchIn(): string { return $this->_searchIn; }
    public function getBoardId(): int { return $this->_boardId; }
    public function getPage(): int { return $this->_page; }
    public function getResults(): array { return $this->_results; }
    public function getTotal(): int { return $this->_total; }
    public function getPageCount(): int { return $this->_pageCount; }
    public function getBoards(): array { return $this->_boards; }

    public function getSearchAction(): string
    {
        return $this->getForumManager()->createUrl('forum/Search');
    }

    public function getResultUrl($record): string
    {
        if ($this->_searchIn === 'posts') {
            return $this->getForumManager()->createUrl('forum/ThreadView', ['post' => $record->id]) . '#post-' . $record->id;
        }
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $record->id]);
    }

    public function getPageUrl(int $p): string
    {
        return $this->getForumManager()->createUrl('forum/Search', [
            'q' => $this->_query, 'in' => $this->_searchIn, 'board' => $this->_boardId, 'p' => $p,
        ]);
    }

}
