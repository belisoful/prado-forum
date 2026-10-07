<?php

/**
 * TForumReply class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;

/**
 * TForumReply renders the quick-reply form at the bottom of a thread.
 *
 * Raises `OnReplyCreated` on valid submission so the host page can persist the post.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumReply extends TForumControl
{
    private $_threadId = 0;
    private $_errors   = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);
        if (!$this->getPage()->getIsPostBack()) {
            $this->getPage()->getSession()->add('forum_reply_start', time());
        }
    }

    public function submitReply($sender, $param): void
    {
        $fm      = $this->getForumManager();
        $request = $this->getRequest();

        $body    = trim($request->getParam('forum_reply_body', ''));
        $elapsed = time() - (int) $this->getPage()->getSession()->get('forum_reply_start', 0);

        $userModule = $this->getApplication()->getModule('users');
        $username   = $userModule ? $userModule->getUser()->getName() : 'guest';

        if (mb_strlen($body) < $fm->getMinPostLength()) {
            $this->_errors[] = 'Reply must be at least ' . $fm->getMinPostLength() . ' characters.';
        }
        if (mb_strlen($body) > $fm->getMaxPostLength()) {
            $this->_errors[] = 'Reply is too long.';
        }
        if ($fm->getEnableSpamProtection()) {
            $eval = $fm->getSpamService()->evaluate('', $body, $username, $elapsed);
            if ($eval['flagged']) {
                $this->_errors[] = 'Your reply has been flagged as potential spam.';
            }
        }
        if ($fm->isRateLimited('post', $username)) {
            $this->_errors[] = 'You are posting too fast. Please wait before replying again.';
        }

        if (empty($this->_errors)) {
            $this->raiseEvent('OnReplyCreated', $this, [
                'thread_id' => $this->_threadId,
                'body'      => $body,
                'username'  => $username,
                'elapsed'   => $elapsed,
            ]);
        }
    }

    // ===================================================================
    // Properties
    // ===================================================================


    public function getThreadId(): int { return $this->_threadId; }
    public function setThreadId(int $v): void { $this->_threadId = $v; }

    public function getErrors(): array { return $this->_errors; }
    public function getHasErrors(): bool { return !empty($this->_errors); }

    public function getThread(): ?TForumThreadRecord
    {
        return $this->_threadId > 0 ? TForumThreadRecord::finder()->findByPk($this->_threadId) : null;
    }

    public function getIsLocked(): bool
    {
        $t = $this->getThread();
        return $t ? (bool) $t->is_locked : false;
    }

}
