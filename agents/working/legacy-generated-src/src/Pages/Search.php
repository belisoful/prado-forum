<?php

/**
 * Search page class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumTagRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;

/**
 * Search is the dedicated full-page search results page.
 *
 * It delegates all search logic to TForumSearch (ShowResults=true).
 * Additionally, if a `?tag=<slug>` parameter is present, it returns all
 * threads tagged with that tag instead of running a text search.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class Search extends TPage
{
    private $_moduleId = 'forum';

    /** @var TForumTagRecord|null set when browsing by tag */
    private $_tag = null;

    /** @var TForumThreadRecord[] threads for tag-browse mode */
    private $_tagThreads = [];

    public function onInit($param): void
    {
        parent::onInit($param);

        $tagSlug = trim($this->getRequest()->getParam('tag', ''));

        if ($tagSlug !== '') {
            $this->_tag = TForumTagRecord::finder()->findByAttributes(['slug' => $tagSlug]);
            if ($this->_tag) {
                $this->loadTagThreads();
                $this->setTitle('Tag: ' . $this->_tag->name . ' — ' . $this->getForumManager()->getSiteName());
                return;
            }
        }

        $query = trim($this->getRequest()->getParam('q', ''));
        $this->setTitle(($query !== '' ? '"' . $query . '" — ' : '') . 'Search — '
            . $this->getForumManager()->getSiteName());
    }

    private function loadTagThreads(): void
    {
        $page    = max(1, (int) $this->getRequest()->getParam('p', 1));
        $perPage = $this->getForumManager()->getThreadsPerPage();
        $offset  = ($page - 1) * $perPage;

        $this->_tagThreads = TForumThreadRecord::finder()->findAll([
            'join'      => "INNER JOIN {$this->getForumManager()->getTable('thread_tags')} tt ON tt.thread_id = t.id",
            'condition' => 'tt.tag_id = :tid AND t.deleted_at IS NULL AND t.is_approved = 1',
            'params'    => [':tid' => $this->_tag->id],
            'alias'     => 't',
            'order'     => 't.last_post_at DESC',
            'limit'     => $perPage,
            'offset'    => $offset,
        ]) ?: [];
    }

    public function getTagRecord(): ?TForumTagRecord { return $this->_tag; }
    public function isTagBrowse(): bool { return $this->_tag !== null; }
    public function getTagThreads(): array { return $this->_tagThreads; }

    public function getThreadUrl(int $threadId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $threadId]);
    }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
