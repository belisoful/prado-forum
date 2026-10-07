<?php

/**
 * TForumSearchService class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Services;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;

/**
 * TForumSearchService provides full-text and native (LIKE) search over
 * threads and posts.
 *
 * When `searchType` is 'fulltext' the service uses MySQL FULLTEXT indexes or
 * PostgreSQL `tsvector` columns.  Otherwise it falls back to LIKE queries
 * which work without schema changes but are slower on large datasets.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumSearchService
{
    /** @var TForumManager */
    private $_manager;

    public function __construct(TForumManager $manager)
    {
        $this->_manager = $manager;
    }

    // ===================================================================
    // Public search API
    // ===================================================================

    /**
     * Search threads by title and (optionally) first-post body.
     *
     * @param string $query      search terms
     * @param int    $boardId    restrict to this board (0 = all boards)
     * @param int    $page       1-based page number
     * @param int    $perPage    results per page (0 = use manager default)
     * @return array{results: TForumThreadRecord[], total: int, pages: int}
     */
    public function searchThreads(string $query, int $boardId = 0, int $page = 1, int $perPage = 0): array
    {
        $perPage = $perPage > 0 ? $perPage : $this->_manager->getThreadsPerPage();
        $offset  = ($page - 1) * $perPage;
        $terms   = $this->sanitizeQuery($query);

        if ($this->_manager->getSearchType() === 'fulltext') {
            return $this->fulltextThreadSearch($terms, $boardId, $perPage, $offset);
        }

        return $this->nativeThreadSearch($terms, $boardId, $perPage, $offset);
    }

    /**
     * Search post bodies.
     *
     * @param string $query
     * @param int    $boardId   0 = global
     * @param int    $page
     * @param int    $perPage
     * @return array{results: TForumPostRecord[], total: int, pages: int}
     */
    public function searchPosts(string $query, int $boardId = 0, int $page = 1, int $perPage = 0): array
    {
        $perPage = $perPage > 0 ? $perPage : $this->_manager->getPostsPerPage();
        $offset  = ($page - 1) * $perPage;
        $terms   = $this->sanitizeQuery($query);

        if ($this->_manager->getSearchType() === 'fulltext') {
            return $this->fulltextPostSearch($terms, $boardId, $perPage, $offset);
        }

        return $this->nativePostSearch($terms, $boardId, $perPage, $offset);
    }

    // ===================================================================
    // LIKE (native) back-end
    // ===================================================================

    private function nativeThreadSearch(string $terms, int $boardId, int $perPage, int $offset): array
    {
        $db   = $this->_manager->getDbConnection();
        $like = '%' . $terms . '%';

        $where  = 'deleted_at IS NULL AND is_approved = 1 AND title LIKE :q';
        $params = [':q' => $like];

        if ($boardId > 0) {
            $where         .= ' AND board_id = :bid';
            $params[':bid'] = $boardId;
        }

        $total = (int) $db->createCommand(
            "SELECT COUNT(*) FROM {$this->_manager->getTable('threads')} WHERE $where"
        )->bindValues($params)->queryScalar();

        $results = TForumThreadRecord::finder()->findAll(
            ['condition' => $where, 'params' => $params, 'limit' => $perPage, 'offset' => $offset, 'order' => 'last_post_at DESC']
        );

        return $this->paginateResult($results ?: [], $total, $perPage);
    }

    private function nativePostSearch(string $terms, int $boardId, int $perPage, int $offset): array
    {
        $db   = $this->_manager->getDbConnection();
        $like = '%' . $terms . '%';

        $where  = 'deleted_at IS NULL AND is_approved = 1 AND content_raw LIKE :q';
        $params = [':q' => $like];

        if ($boardId > 0) {
            $where         .= ' AND board_id = :bid';
            $params[':bid'] = $boardId;
        }

        $total = (int) $db->createCommand(
            "SELECT COUNT(*) FROM {$this->_manager->getTable('posts')} WHERE $where"
        )->bindValues($params)->queryScalar();

        $results = TForumPostRecord::finder()->findAll(
            ['condition' => $where, 'params' => $params, 'limit' => $perPage, 'offset' => $offset, 'order' => 'created_at DESC']
        );

        return $this->paginateResult($results ?: [], $total, $perPage);
    }

    // ===================================================================
    // FULLTEXT back-end
    // ===================================================================

    private function fulltextThreadSearch(string $terms, int $boardId, int $perPage, int $offset): array
    {
        $db     = $this->_manager->getDbConnection();
        $table  = $this->_manager->getTable('threads');
        $driver = strtolower($db->getDriverName());

        if (str_contains($driver, 'mysql') || str_contains($driver, 'mariadb')) {
            $matchExpr = "MATCH(title) AGAINST(:q IN BOOLEAN MODE)";
            $where     = "deleted_at IS NULL AND is_approved = 1 AND $matchExpr";
        } else {
            // PostgreSQL tsvector
            $matchExpr = "to_tsvector('english', title) @@ plainto_tsquery('english', :q)";
            $where     = "deleted_at IS NULL AND is_approved = 1 AND $matchExpr";
        }

        $params = [':q' => $terms];
        if ($boardId > 0) {
            $where         .= ' AND board_id = :bid';
            $params[':bid'] = $boardId;
        }

        $total   = (int) $db->createCommand("SELECT COUNT(*) FROM $table WHERE $where")->bindValues($params)->queryScalar();
        $results = TForumThreadRecord::finder()->findAll(
            ['condition' => $where, 'params' => $params, 'limit' => $perPage, 'offset' => $offset]
        );

        return $this->paginateResult($results ?: [], $total, $perPage);
    }

    private function fulltextPostSearch(string $terms, int $boardId, int $perPage, int $offset): array
    {
        $db     = $this->_manager->getDbConnection();
        $table  = $this->_manager->getTable('posts');
        $driver = strtolower($db->getDriverName());

        if (str_contains($driver, 'mysql') || str_contains($driver, 'mariadb')) {
            $matchExpr = "MATCH(content_raw) AGAINST(:q IN BOOLEAN MODE)";
        } else {
            $matchExpr = "to_tsvector('english', content_raw) @@ plainto_tsquery('english', :q)";
        }

        $where  = "deleted_at IS NULL AND is_approved = 1 AND $matchExpr";
        $params = [':q' => $terms];

        if ($boardId > 0) {
            $where         .= ' AND board_id = :bid';
            $params[':bid'] = $boardId;
        }

        $total   = (int) $db->createCommand("SELECT COUNT(*) FROM $table WHERE $where")->bindValues($params)->queryScalar();
        $results = TForumPostRecord::finder()->findAll(
            ['condition' => $where, 'params' => $params, 'limit' => $perPage, 'offset' => $offset]
        );

        return $this->paginateResult($results ?: [], $total, $perPage);
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function sanitizeQuery(string $query): string
    {
        // Strip characters that could break SQL LIKE or fulltext syntax.
        return preg_replace('/[%_\\\\]/', '', trim($query));
    }

    /**
     * @param array $results raw record array
     * @param int   $total   total matching rows
     * @param int   $perPage
     * @return array{results: array, total: int, pages: int}
     */
    private function paginateResult(array $results, int $total, int $perPage): array
    {
        return [
            'results' => $results,
            'total'   => $total,
            'pages'   => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }
}
