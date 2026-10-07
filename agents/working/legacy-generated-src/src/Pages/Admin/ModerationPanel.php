<?php

/**
 * ModerationPanel page class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages\Admin;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumModerationRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * ModerationPanel provides a dedicated moderation interface for moderators.
 *
 * Unlike the full ForumAdmin panel (which requires 'ForumAdmin' role),
 * this page is accessible to users with 'ForumModerator' role.
 *
 * Queues handled:
 *   - Pending posts (is_approved = 0)
 *   - Spam-flagged posts (is_spam_flagged = 1)
 *   - Reported posts (linked via moderation log with action_type = 'report')
 *   - Recent mod log entries authored by the current moderator
 *
 * Actions available per post:
 *   - Approve, Reject (soft-delete), Mark as Spam, Clear Spam Flag
 *   - Move post to another thread (if moderator)
 *   - Warn user, Ban user (if forum admin)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class ModerationPanel extends TPage
{
    private $_moduleId = 'forum';

    /** @var string current queue tab */
    private $_queue = 'pending';

    /** @var TForumPostRecord[] */
    private $_posts = [];

    /** @var TForumModerationRecord[] recent log entries for this moderator */
    private $_myLog = [];

    /** @var int total count for current queue (untruncated) */
    private $_totalCount = 0;

    public function onInit($param): void
    {
        parent::onInit($param);

        // Require at least moderator role.
        $users = $this->getApplication()->getModule('users');
        if (!$users || (!$users->isForumModerator() && !$users->isForumAdmin())) {
            $this->getResponse()->redirect(
                $this->getForumManager()->createUrl('forum/ForumHome')
            );
            return;
        }

        $this->_queue = $this->getRequest()->getParam('queue', 'pending');
        $this->setTitle('Moderation — ' . $this->getForumManager()->getSiteName());

        $this->loadQueue();
        $this->loadMyLog();
    }

    // ===================================================================
    // Data loaders
    // ===================================================================

    private function loadQueue(): void
    {
        switch ($this->_queue) {
            case 'spam':
                $this->_posts = TForumPostRecord::finder()->with('author', 'thread')->findAll([
                    'condition' => 'is_spam_flagged = 1 AND deleted_at IS NULL',
                    'order'     => 'spam_score DESC, created_at ASC',
                    'limit'     => 100,
                ]) ?: [];
                break;

            case 'reported':
                // Posts that have been user-reported via moderation log
                $db  = $this->getForumManager()->getDbConnection();
                $tbl = $this->getForumManager()->getTable('moderation_log');
                $ids = $db->createCommand(
                    "SELECT DISTINCT target_id FROM {$tbl}"
                    . " WHERE action_type = 'report' AND target_type = 'post'"
                    . " ORDER BY MAX(created_at) DESC LIMIT 100"
                )->queryColumn();
                $this->_posts = $ids
                    ? TForumPostRecord::finder()->with('author', 'thread')->findAll(
                        ['condition' => 'id IN (' . implode(',', array_map('intval', $ids)) . ') AND deleted_at IS NULL']
                    ) ?: []
                    : [];
                break;

            default: // 'pending'
                $this->_posts = TForumPostRecord::finder()->with('author', 'thread')->findAll([
                    'condition' => 'is_approved = 0 AND is_spam_flagged = 0 AND deleted_at IS NULL',
                    'order'     => 'created_at ASC',
                    'limit'     => 100,
                ]) ?: [];
        }

        $this->_totalCount = count($this->_posts);
    }

    private function loadMyLog(): void
    {
        $users = $this->getApplication()->getModule('users');
        $profile = $users ? $users->getForumProfile() : null;
        if (!$profile) {
            return;
        }

        $this->_myLog = TForumModerationRecord::finder()->findAll([
            'condition' => 'moderator_user_id = :mid',
            'params'    => [':mid' => $profile->id],
            'order'     => 'created_at DESC',
            'limit'     => 20,
        ]) ?: [];
    }

    // ===================================================================
    // Action handlers
    // ===================================================================

    /**
     * Approve a pending post.
     */
    public function approvePost($sender, $param): void
    {
        $id = (int) $this->getRequest()->getParam('post_id', 0);
        if ($id > 0) {
            $post = TForumPostRecord::finder()->findByPk($id);
            if ($post) {
                $post->is_approved = 1;
                $post->save();
                $this->logModAction('approve', 'post', $id, 'Approved by moderator.');
            }
        }
        $this->redirectBack();
    }

    /**
     * Soft-delete (reject) a post.
     */
    public function rejectPost($sender, $param): void
    {
        $id     = (int) $this->getRequest()->getParam('post_id', 0);
        $reason = trim($this->getRequest()->getParam('reason', ''));
        if ($id > 0) {
            $post = TForumPostRecord::finder()->findByPk($id);
            if ($post) {
                $post->deleted_at = date('Y-m-d H:i:s');
                $post->save();
                $this->logModAction('delete', 'post', $id, $reason ?: 'Rejected by moderator.');
            }
        }
        $this->redirectBack();
    }

    /**
     * Mark a post as spam (flag + soft-delete).
     */
    public function markSpam($sender, $param): void
    {
        $id = (int) $this->getRequest()->getParam('post_id', 0);
        if ($id > 0) {
            $post = TForumPostRecord::finder()->findByPk($id);
            if ($post) {
                $post->is_spam_flagged = 1;
                $post->deleted_at     = date('Y-m-d H:i:s');
                $post->save();
                $this->logModAction('spam', 'post', $id, 'Marked as spam by moderator.');
            }
        }
        $this->redirectBack();
    }

    /**
     * Clear spam flag and approve a post.
     */
    public function clearSpam($sender, $param): void
    {
        $id = (int) $this->getRequest()->getParam('post_id', 0);
        if ($id > 0) {
            $post = TForumPostRecord::finder()->findByPk($id);
            if ($post) {
                $post->is_spam_flagged = 0;
                $post->spam_score      = 0;
                $post->is_approved     = 1;
                $post->deleted_at      = null;
                $post->save();
                $this->logModAction('clear_spam', 'post', $id, 'Spam cleared by moderator.');
            }
        }
        $this->redirectBack();
    }

    /**
     * Soft-delete an entire thread.
     */
    public function deleteThread($sender, $param): void
    {
        $id     = (int) $this->getRequest()->getParam('thread_id', 0);
        $reason = trim($this->getRequest()->getParam('reason', ''));
        if ($id > 0) {
            $thread = TForumThreadRecord::finder()->findByPk($id);
            if ($thread) {
                $thread->deleted_at = date('Y-m-d H:i:s');
                $thread->save();
                $this->logModAction('delete_thread', 'thread', $id, $reason ?: 'Deleted by moderator.');
            }
        }
        $this->redirectBack();
    }

    /**
     * Warn a user (increment warn_count on their profile).
     */
    public function warnUser($sender, $param): void
    {
        $userId = (int) $this->getRequest()->getParam('warn_user_id', 0);
        $reason = trim($this->getRequest()->getParam('reason', ''));
        if ($userId > 0) {
            $profile = TForumUserProfileRecord::finder()->findByPk($userId);
            if ($profile) {
                $profile->warn_count = ($profile->warn_count ?? 0) + 1;
                $profile->save();
                $this->logModAction('warn', 'user', $userId, $reason ?: 'Warning issued by moderator.');
            }
        }
        $this->redirectBack();
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function logModAction(string $actionType, string $targetType, int $targetId, string $reason): void
    {
        $users   = $this->getApplication()->getModule('users');
        $profile = $users ? $users->getForumProfile() : null;

        $log                  = new TForumModerationRecord();
        $log->moderator_user_id = $profile ? $profile->id : null;
        $log->action_type     = $actionType;
        $log->target_type     = $targetType;
        $log->target_id       = $targetId;
        $log->reason          = $reason;
        $log->created_at      = date('Y-m-d H:i:s');
        $log->save();
    }

    private function redirectBack(): void
    {
        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl(
                'forum/Admin/ModerationPanel',
                ['queue' => $this->_queue]
            )
        );
    }

    // ===================================================================
    // Properties
    // ===================================================================

    public function getQueue(): string { return $this->_queue; }
    public function getPosts(): array { return $this->_posts; }
    public function getTotalCount(): int { return $this->_totalCount; }
    public function getMyLog(): array { return $this->_myLog; }

    public function getQueueUrl(string $queue): string
    {
        return $this->getForumManager()->createUrl('forum/Admin/ModerationPanel', ['queue' => $queue]);
    }

    public function getPostUrl(int $postId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['post' => $postId]) . '#post-' . $postId;
    }

    public function getThreadUrl(int $threadId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $threadId]);
    }

    public function getAdminUrl(string $panel = 'dashboard'): string
    {
        return $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => $panel]);
    }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
