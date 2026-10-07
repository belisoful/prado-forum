<?php

/**
 * ForumHome page class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;

/**
 * ForumHome is the forum index page.
 *
 * It displays all categories and their boards via TForumCategories,
 * and sidebar widgets (stats, recent posts, tag cloud, search).
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class ForumHome extends TPage
{
    /** @var string forum manager module ID */
    private $_moduleId = 'forum';

    public function onInit($param): void
    {
        parent::onInit($param);
        $this->setTitle($this->getForumManager()->getSiteName());
    }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }

    public function getNewThreadUrl(): string
    {
        return $this->getForumManager()->createUrl('forum/NewThread');
    }

    public function getRSSUrl(): string
    {
        return $this->getForumManager()->createUrl('forum/Feed', ['type' => 'global', 'format' => 'atom']);
    }
}
