<?php

/**
 * ForumView page class file — displays a single board (thread list).
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumBoardRecord;

/**
 * ForumView shows the thread list for a given board identified by `?id=`.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class ForumView extends TPage
{
    private $_moduleId = 'forum';

    /** @var TForumBoardRecord|null */
    private $_board = null;

    private $_page = 1;

    public function onInit($param): void
    {
        parent::onInit($param);

        $boardId = (int) $this->getRequest()->getParam('id', 0);
        $this->_page = max(1, (int) $this->getRequest()->getParam('p', 1));

        if ($boardId > 0) {
            $this->_board = TForumBoardRecord::finder()->findByPk($boardId);
        }

        if ($this->_board) {
            $this->setTitle($this->_board->name . ' — ' . $this->getForumManager()->getSiteName());

            // Increment view counter via the thread list portlet configuration.
            $portlet = $this->findControl('ThreadList');
            if ($portlet) {
                $portlet->setBoardId($boardId);
                $portlet->setPage($this->_page);
            }

            // Set breadcrumbs.
            $bc = $this->findControl('Breadcrumbs');
            if ($bc) {
                $bc->addCrumb('Forum', $this->getForumManager()->createUrl('forum/ForumHome'));
                if ($this->_board->category) {
                    $bc->addCrumb($this->_board->category->name, null);
                }
                $bc->addCrumb($this->_board->name, null);
            }
        }
    }

    public function getBoard(): ?TForumBoardRecord { return $this->_board; }
    public function getCurrentPage(): int { return $this->_page; }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
