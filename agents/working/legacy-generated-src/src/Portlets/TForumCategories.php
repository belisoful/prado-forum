<?php

/**
 * TForumCategories class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumCategoryRecord;

/**
 * TForumCategories renders the full forum index: all active categories
 * and the boards nested inside each one, along with board statistics and
 * the last-post summary.
 *
 * Used on the ForumHome page.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumCategories extends TForumControl
{
    /** @var string module ID of TForumManager */

    /** @var TForumCategoryRecord[]|null loaded categories with their boards */
    private $_categories = null;

    public function onLoad($param): void
    {
        parent::onLoad($param);
        $this->loadCategories();
    }

    private function loadCategories(): void
    {
        $this->_categories = TForumCategoryRecord::finder()->with('boards')->findAll(
            ['condition' => 'is_active = 1', 'order' => 'sort_order ASC, id ASC']
        ) ?: [];
    }

    public function getCategories(): array
    {
        return $this->_categories ?? [];
    }



    public function getBoardUrl(int $boardId): string
    {
        return $this->getForumManager()->createUrl('forum/ForumView', ['id' => $boardId]);
    }

    public function getThreadUrl(int $threadId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $threadId]);
    }
}
