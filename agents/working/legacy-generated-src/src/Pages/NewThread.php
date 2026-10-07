<?php

/**
 * NewThread page class file.
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
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;
use Belisoful\Forum\ActiveRecord\TForumTagRecord;
use Belisoful\Forum\ActiveRecord\TForumPollRecord;
use Belisoful\Forum\ActiveRecord\TForumPollOptionRecord;

/**
 * NewThread handles creation of a new thread with an optional poll.
 *
 * URL param: `?board=<boardId>`
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class NewThread extends TPage
{
    private $_moduleId = 'forum';
    private $_boardId  = 0;

    /** @var TForumBoardRecord|null */
    private $_board = null;

    public function onInit($param): void
    {
        parent::onInit($param);

        $this->_boardId = (int) $this->getRequest()->getParam('board', 0);

        if ($this->_boardId > 0) {
            $this->_board = TForumBoardRecord::finder()->findByPk($this->_boardId);
        }

        if ($this->_board) {
            $this->setTitle('New Thread — ' . $this->_board->name);

            // Configure the form portlet.
            $portlet = $this->findControl('NewThreadForm');
            if ($portlet) {
                $portlet->setBoardId($this->_boardId);
                $portlet->attachEventHandler('OnThreadCreated', [$this, 'onThreadCreated']);
            }

            // Breadcrumbs.
            $bc = $this->findControl('Breadcrumbs');
            if ($bc) {
                $fm = $this->getForumManager();
                $bc->addCrumb('Forum', $fm->createUrl('forum/ForumHome'));
                $bc->addCrumb($this->_board->name, $fm->createUrl('forum/ForumView', ['id' => $this->_boardId]));
                $bc->addCrumb('New Thread', null);
            }
        }
    }

    /**
     * Persist the new thread and its first post (raised by TForumNewThread).
     */
    public function onThreadCreated($sender, $param): void
    {
        $fm       = $this->getForumManager();
        $boardId  = (int) $param['board_id'];
        $title    = $param['title'];
        $body     = $param['body'];
        $tags     = $param['tags'] ?? [];
        $username = $param['username'];

        $profile = TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]);
        if (!$profile) {
            return;
        }

        $html = $fm->parseContent($body);

        // Create thread.
        $thread              = new TForumThreadRecord();
        $thread->board_id    = $boardId;
        $thread->user_id     = $profile->id;
        $thread->title       = $title;
        $thread->is_approved = $fm->isPreModeration() ? 0 : 1;
        $thread->created_at  = date('Y-m-d H:i:s');
        $thread->updated_at  = date('Y-m-d H:i:s');
        $thread->save();

        // Create first post.
        $post               = new TForumPostRecord();
        $post->thread_id    = $thread->id;
        $post->board_id     = $boardId;
        $post->user_id      = $profile->id;
        $post->content_raw  = $body;
        $post->content_html = $html;
        $post->is_first_post = 1;
        $post->is_approved  = $thread->is_approved;
        $post->created_at   = date('Y-m-d H:i:s');
        $post->updated_at   = date('Y-m-d H:i:s');
        $post->save();

        // Link thread last-post.
        $db = $fm->getDbConnection();
        $db->createCommand(
            "UPDATE {$fm->getTable('threads')} SET last_post_id = :pid, last_post_user_id = :uid, last_post_at = NOW() WHERE id = :tid"
        )->bindValues([':pid' => $post->id, ':uid' => $profile->id, ':tid' => $thread->id])->execute();

        // Board counters.
        $db->createCommand(
            "UPDATE {$fm->getTable('boards')} SET thread_count = thread_count + 1, post_count = post_count + 1,
             last_post_id = :pid, last_post_user_id = :uid, last_post_at = NOW() WHERE id = :bid"
        )->bindValues([':pid' => $post->id, ':uid' => $profile->id, ':bid' => $boardId])->execute();

        // User counters.
        $db->createCommand(
            "UPDATE {$fm->getTable('user_profiles')} SET thread_count = thread_count + 1, post_count = post_count + 1, last_post_at = NOW() WHERE id = :uid"
        )->bindValue(':uid', $profile->id)->execute();

        // Tags.
        if ($fm->getEnableTags() && !empty($tags)) {
            $this->saveTags($thread->id, $tags, $fm);
        }

        // Poll.
        $this->savePoll($thread->id);

        $fm->setRateLimit('thread', $username);
        $fm->getNotificationService()->flushQueue();

        $this->getResponse()->redirect(
            $fm->createUrl('forum/ThreadView', ['id' => $thread->id])
        );
    }

    private function saveTags(int $threadId, array $tags, TForumManager $fm): void
    {
        $max = $fm->getMaxTagsPerThread();
        $tags = array_slice($tags, 0, $max);

        foreach ($tags as $tagName) {
            if ($tagName === '') {
                continue;
            }
            $slug = TForumTagRecord::slugify($tagName);
            $tag  = TForumTagRecord::finder()->findByAttributes(['slug' => $slug]);
            if (!$tag) {
                $tag              = new TForumTagRecord();
                $tag->name        = $tagName;
                $tag->slug        = $slug;
                $tag->thread_count = 0;
                $tag->created_at  = date('Y-m-d H:i:s');
                $tag->save();
            }

            // Insert pivot.
            $db = $fm->getDbConnection();
            $db->createCommand(
                "INSERT IGNORE INTO {$fm->getTable('thread_tags')} (thread_id, tag_id) VALUES (:tid, :gid)"
            )->bindValues([':tid' => $threadId, ':gid' => $tag->id])->execute();

            // Increment tag counter.
            $db->createCommand(
                "UPDATE {$fm->getTable('tags')} SET thread_count = thread_count + 1 WHERE id = :id"
            )->bindValue(':id', $tag->id)->execute();
        }
    }

    private function savePoll(int $threadId): void
    {
        $request  = $this->getRequest();
        $question = trim($request->getParam('poll_question', ''));
        if ($question === '' || !$this->getForumManager()->getEnablePolls()) {
            return;
        }

        $poll                    = new TForumPollRecord();
        $poll->thread_id         = $threadId;
        $poll->question          = $question;
        $poll->is_multiple_choice = (int) (bool) $request->getParam('poll_multiple', 0);
        $poll->allow_change_vote = 1;
        $closesAt                = trim($request->getParam('poll_closes_at', ''));
        $poll->closes_at         = $closesAt !== '' ? date('Y-m-d H:i:s', strtotime($closesAt)) : null;
        $poll->created_at        = date('Y-m-d H:i:s');
        $poll->updated_at        = date('Y-m-d H:i:s');
        $poll->save();

        for ($i = 1; $i <= 10; $i++) {
            $optText = trim($request->getParam('poll_opt_' . $i, ''));
            if ($optText === '') {
                continue;
            }
            $opt               = new TForumPollOptionRecord();
            $opt->poll_id      = $poll->id;
            $opt->option_text  = $optText;
            $opt->sort_order   = $i;
            $opt->created_at   = date('Y-m-d H:i:s');
            $opt->save();
        }
    }

    public function getBoard(): ?TForumBoardRecord { return $this->_board; }
    public function getBoardId(): int { return $this->_boardId; }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
