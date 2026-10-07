<?php

/**
 * TForumPostList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumReactionRecord;

/**
 * TForumPostList renders the paginated list of posts within a thread.
 *
 * Each post shows: author avatar, display name, post count, reputation,
 * post body (HTML), reactions summary, and edit/quote/report action links.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPostList extends TForumControl
{
    private $_threadId = 0;
    private $_page     = 1;

    /** @var TForumThreadRecord|null */
    private $_thread = null;

    /** @var TForumPostRecord[] */
    private $_posts = [];

    /** @var int */
    private $_total = 0;

    /** @var int */
    private $_pageCount = 1;

    /** @var array reaction counts keyed by post_id */
    private $_reactions = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);
        if ($this->_threadId > 0) {
            $this->loadPosts();
        }
    }

    private function loadPosts(): void
    {
        $fm  = $this->getForumManager();

        $this->_thread = TForumThreadRecord::finder()->findByPk($this->_threadId);

        $perPage = $fm->getPostsPerPage();
        $offset  = ($this->_page - 1) * $perPage;

        $cond   = 'thread_id = :tid AND deleted_at IS NULL AND is_approved = 1';
        $params = [':tid' => $this->_threadId];

        $this->_total     = (int) TForumPostRecord::finder()->count($cond, $params);
        $this->_pageCount = max(1, (int) ceil($this->_total / $perPage));

        $this->_posts = TForumPostRecord::finder()->with('author', 'reactions')->findAll([
            'condition' => $cond,
            'params'    => $params,
            'order'     => 'created_at ASC',
            'limit'     => $perPage,
            'offset'    => $offset,
        ]) ?: [];

        // Build reaction summary map.
        foreach ($this->_posts as $post) {
            $this->_reactions[$post->id] = $this->buildReactionSummary($post->reactions ?? []);
        }
    }

    /**
     * Aggregate reaction records into a count-by-type map.
     *
     * @param TForumReactionRecord[] $reactions
     * @return array e.g. ['like' => 3, 'helpful' => 1]
     */
    private function buildReactionSummary(array $reactions): array
    {
        $summary = [];
        foreach ($reactions as $r) {
            $summary[$r->type] = ($summary[$r->type] ?? 0) + 1;
        }
        return $summary;
    }

    // ===================================================================
    // Properties
    // ===================================================================


    public function getThreadId(): int { return $this->_threadId; }
    public function setThreadId(int $v): void { $this->_threadId = $v; }

    public function getPage(): int { return $this->_page; }
    public function setPage(int $v): void { $this->_page = max(1, $v); }

    public function getThread(): ?TForumThreadRecord { return $this->_thread; }
    public function getPosts(): array { return $this->_posts; }
    public function getTotal(): int { return $this->_total; }
    public function getPageCount(): int { return $this->_pageCount; }

    public function getReactionSummary(int $postId): array
    {
        return $this->_reactions[$postId] ?? [];
    }


    public function getPageUrl(int $p): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $this->_threadId, 'p' => $p]);
    }

    public function getEditUrl(int $postId): string
    {
        return $this->getForumManager()->createUrl('forum/EditPost', ['id' => $postId]);
    }

    public function getReactUrl(int $postId): string
    {
        return $this->getForumManager()->createUrl('forum/React', ['post' => $postId]);
    }

    public function getReportUrl(int $postId): string
    {
        return $this->getForumManager()->createUrl('forum/Report', ['post' => $postId]);
    }

    /** @return string[] enabled reaction type slugs */
    public function getReactionTypes(): array
    {
        return TForumReactionRecord::TYPES;
    }
}
