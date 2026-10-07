<?php

/**
 * EditPost page class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumPostHistoryRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumModerationRecord;

/**
 * EditPost provides an in-place editor for an existing forum post.
 *
 * Access rules:
 *   - The logged-in user must be the post author, a forum moderator,
 *     or a forum admin.
 *   - Editing a post that belongs to a locked thread is forbidden
 *     unless the user is a moderator or admin.
 *   - A grace-period edit (within the configured window) does NOT
 *     create a history record; edits outside the window DO.
 *
 * URL param: `?id=<postId>`
 *
 * On success, redirects back to the post anchor in its thread.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class EditPost extends TPage
{
    private $_moduleId = 'forum';

    /** @var TForumPostRecord|null */
    private $_post = null;

    /** @var TForumThreadRecord|null */
    private $_thread = null;

    /** @var string validation error message */
    private $_error = '';

    /** @var bool whether the current user may edit this post */
    private $_canEdit = false;

    public function onInit($param): void
    {
        parent::onInit($param);

        $postId = (int) $this->getRequest()->getParam('id', 0);
        if ($postId <= 0) {
            $this->redirectHome();
            return;
        }

        $this->_post = TForumPostRecord::finder()->with('author')->findByPk($postId);
        if (!$this->_post || $this->_post->deleted_at !== null) {
            $this->redirectHome();
            return;
        }

        $this->_thread = TForumThreadRecord::finder()->findByPk($this->_post->thread_id);
        if (!$this->_thread || $this->_thread->deleted_at !== null) {
            $this->redirectHome();
            return;
        }

        // Determine editability.
        $users = $this->getApplication()->getModule('users');
        if (!$users) {
            $this->redirectHome();
            return;
        }

        $profile = $users->getForumProfile();
        $isMod   = $users->isForumModerator() || $users->isForumAdmin();
        $isAuthor = $profile && ($profile->id === $this->_post->user_id);

        if (!$isMod && !$isAuthor) {
            $this->redirectThread();
            return;
        }

        if ($this->_thread->is_locked && !$isMod) {
            $this->_error = 'This thread is locked and cannot be edited.';
        }

        $this->_canEdit = ($this->_error === '');
        $this->setTitle('Edit Post — ' . $this->getForumManager()->getSiteName());
    }

    // ===================================================================
    // Action handlers
    // ===================================================================

    /**
     * Handle form submission: validate, save edit, write history if needed.
     */
    public function saveEdit($sender, $param): void
    {
        if (!$this->_canEdit || !$this->_post) {
            return;
        }

        $newRaw  = trim($this->getRequest()->getParam('content_raw', ''));
        $editNote = trim($this->getRequest()->getParam('edit_note', ''));

        if ($newRaw === '') {
            $this->_error = 'Post body cannot be empty.';
            return;
        }

        $fm = $this->getForumManager();

        // Determine grace period (no history record within N minutes of original post).
        $gracePeriodMinutes = 5;
        $ageSeconds = time() - strtotime($this->_post->created_at);
        $outsideGrace = ($ageSeconds > ($gracePeriodMinutes * 60));

        // Snapshot history when outside grace period and content actually changed.
        if ($outsideGrace && $newRaw !== $this->_post->content_raw) {
            $history                  = new TForumPostHistoryRecord();
            $history->post_id         = $this->_post->id;
            $history->edited_by_user_id = $this->getCurrentUserId();
            $history->content_raw     = $this->_post->content_raw;
            $history->content_html    = $this->_post->content_html;
            $history->edit_note       = $editNote ?: null;
            $history->created_at      = date('Y-m-d H:i:s');
            $history->save();
        }

        // Re-render HTML from the new BBCode.
        $newHtml = $fm->parseContent($newRaw);

        $this->_post->content_raw   = $newRaw;
        $this->_post->content_html  = $newHtml;
        $this->_post->updated_at    = date('Y-m-d H:i:s');
        $this->_post->edit_count    = ($this->_post->edit_count ?? 0) + 1;
        $this->_post->last_edited_by_user_id = $this->getCurrentUserId();
        $this->_post->save();

        // Write moderation log if edit was performed by mod/admin, not the author.
        $users   = $this->getApplication()->getModule('users');
        $profile = $users ? $users->getForumProfile() : null;
        if ($profile && $profile->id !== $this->_post->user_id) {
            $log = new TForumModerationRecord();
            $log->moderator_user_id = $profile->id;
            $log->action_type       = 'edit_post';
            $log->target_type       = 'post';
            $log->target_id         = $this->_post->id;
            $log->reason            = $editNote ?: 'Edited by moderator.';
            $log->created_at        = date('Y-m-d H:i:s');
            $log->save();
        }

        $this->redirectPost();
    }

    /**
     * Cancel — redirect back to the post without saving.
     */
    public function cancelEdit($sender, $param): void
    {
        $this->redirectPost();
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function getCurrentUserId(): ?int
    {
        $users   = $this->getApplication()->getModule('users');
        $profile = $users ? $users->getForumProfile() : null;
        return $profile ? $profile->id : null;
    }

    private function redirectPost(): void
    {
        if ($this->_post) {
            $url = $this->getForumManager()->createUrl('forum/ThreadView', ['post' => $this->_post->id])
                 . '#post-' . $this->_post->id;
            $this->getResponse()->redirect($url);
        } else {
            $this->redirectHome();
        }
    }

    private function redirectThread(): void
    {
        if ($this->_thread) {
            $url = $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $this->_thread->id]);
            $this->getResponse()->redirect($url);
        } else {
            $this->redirectHome();
        }
    }

    private function redirectHome(): void
    {
        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/ForumHome')
        );
    }

    // ===================================================================
    // Properties
    // ===================================================================

    public function getPost(): ?TForumPostRecord { return $this->_post; }
    public function getThread(): ?TForumThreadRecord { return $this->_thread; }
    public function getError(): string { return $this->_error; }
    public function getCanEdit(): bool { return $this->_canEdit; }

    public function getThreadUrl(): string
    {
        return $this->_thread
            ? $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $this->_thread->id])
            : $this->getForumManager()->createUrl('forum/ForumHome');
    }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
