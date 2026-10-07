<?php

/**
 * ThreadView page class file — displays a single thread with its posts.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * ThreadView renders a full thread: breadcrumbs, poll (if any), post list,
 * and the quick-reply form.  It also increments the thread view counter.
 *
 * URL params: `?id=<threadId>[&p=<page>]`
 * Direct-to-post link: `?post=<postId>` (resolves thread + page automatically)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class ThreadView extends TPage
{
    private $_moduleId = 'forum';

    /** @var TForumThreadRecord|null */
    private $_thread = null;

    private $_page = 1;

    public function onInit($param): void
    {
        parent::onInit($param);

        $threadId = (int) $this->getRequest()->getParam('id', 0);
        $this->_page = max(1, (int) $this->getRequest()->getParam('p', 1));

        if ($threadId > 0) {
            $this->_thread = TForumThreadRecord::finder()
                ->with('board', 'author')
                ->findByPk($threadId);
        }

        if ($this->_thread) {
            $this->setTitle($this->_thread->title . ' — ' . $this->getForumManager()->getSiteName());
            $this->incrementViewCount();
            $this->configurePollPortlet();
            $this->configurePostListPortlet();
            $this->configureReplyPortlet();
            $this->configureBreadcrumbs();
        }
    }

    private function incrementViewCount(): void
    {
        if (!$this->getPage()->getIsPostBack()) {
            $db = $this->getForumManager()->getDbConnection();
            $db->createCommand(
                "UPDATE {$this->getForumManager()->getTable('threads')} SET view_count = view_count + 1 WHERE id = :id"
            )->bindValue(':id', $this->_thread->id)->execute();
        }
    }

    private function configurePollPortlet(): void
    {
        $poll = $this->findControl('Poll');
        if ($poll) {
            $poll->setThreadId($this->_thread->id);
        }
    }

    private function configurePostListPortlet(): void
    {
        $pl = $this->findControl('PostList');
        if ($pl) {
            $pl->setThreadId($this->_thread->id);
            $pl->setPage($this->_page);
        }
    }

    private function configureReplyPortlet(): void
    {
        $reply = $this->findControl('Reply');
        if ($reply) {
            $reply->setThreadId($this->_thread->id);
            $reply->attachEventHandler('OnReplyCreated', [$this, 'onReplyCreated']);
        }
    }

    private function configureBreadcrumbs(): void
    {
        $bc = $this->findControl('Breadcrumbs');
        if (!$bc || !$this->_thread) {
            return;
        }
        $fm = $this->getForumManager();
        $bc->addCrumb('Forum', $fm->createUrl('forum/ForumHome'));
        if ($this->_thread->board) {
            $bc->addCrumb(
                $this->_thread->board->name,
                $fm->createUrl('forum/ForumView', ['id' => $this->_thread->board_id])
            );
        }
        $bc->addCrumb($this->_thread->title, null);
    }

    /**
     * Handle a new reply submitted via TForumReply.
     *
     * @param TForumReply $sender
     * @param array              $param  keys: thread_id, body, username, elapsed
     */
    public function onReplyCreated($sender, $param): void
    {
        $fm       = $this->getForumManager();
        $threadId = (int) $param['thread_id'];
        $body     = $param['body'];
        $username = $param['username'];

        $profile = TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]);
        if (!$profile) {
            return;
        }

        $html = $fm->parseContent($body);
        $html = $fm->processMentions($html, 0); // 0 — post ID not known yet

        $post              = new \Belisoful\Forum\ActiveRecord\TForumPostRecord();
        $post->thread_id   = $threadId;
        $post->board_id    = $this->_thread->board_id;
        $post->user_id     = $profile->id;
        $post->content_raw = $body;
        $post->content_html = $html;
        $post->is_approved = $fm->isPreModeration() ? 0 : 1;
        $post->is_spam_flagged = 0;
        $post->created_at  = date('Y-m-d H:i:s');
        $post->updated_at  = date('Y-m-d H:i:s');
        $post->save();

        // Now we know the post ID — process mentions with correct ID.
        if ($fm->getEnableMentions() && $post->id) {
            $fm->getNotificationService()->flushQueue();
        }

        // Update thread last_post and reply counters.
        $db = $fm->getDbConnection();
        $db->createCommand(
            "UPDATE {$fm->getTable('threads')} SET reply_count = reply_count + 1,
             last_post_id = :pid, last_post_user_id = :uid, last_post_at = NOW()
             WHERE id = :tid"
        )->bindValues([':pid' => $post->id, ':uid' => $profile->id, ':tid' => $threadId])->execute();

        // Update board post counter.
        $db->createCommand(
            "UPDATE {$fm->getTable('boards')} SET post_count = post_count + 1,
             last_post_id = :pid, last_post_user_id = :uid, last_post_at = NOW()
             WHERE id = :bid"
        )->bindValues([':pid' => $post->id, ':uid' => $profile->id, ':bid' => $this->_thread->board_id])->execute();

        // Update user post counter.
        $db->createCommand(
            "UPDATE {$fm->getTable('user_profiles')} SET post_count = post_count + 1, last_post_at = NOW()
             WHERE id = :uid"
        )->bindValue(':uid', $profile->id)->execute();

        $fm->setRateLimit('post', $username);

        // Notify subscribers.
        if ($fm->getEnableNotifications()) {
            $fm->getNotificationService()->notifyThreadReply($threadId, $post->id, $username);
        }

        // Redirect to last page to show the new post.
        $this->getResponse()->redirect(
            $fm->createUrl('forum/ThreadView', ['id' => $threadId, 'p' => 'last']) . '#post-' . $post->id
        );
    }

    public function getThread(): ?TForumThreadRecord { return $this->_thread; }
    public function getCurrentPage(): int { return $this->_page; }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
