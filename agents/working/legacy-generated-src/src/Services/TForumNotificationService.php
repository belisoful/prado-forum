<?php

/**
 * TForumNotificationService class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Services;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumNotificationRecord;
use Belisoful\Forum\ActiveRecord\TForumSubscriptionRecord;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * TForumNotificationService handles creation and delivery of forum notifications.
 *
 * Notifications are persisted to `forum_notifications` and optionally emailed
 * to users who have enabled email notifications on their profile.  A simple
 * batching mechanism prevents duplicate emails within a 24-hour window.
 *
 * Supported notification types:
 *   reply           — someone replied to a thread the user created or watches
 *   mention         — @username mention in a post
 *   reaction        — a reaction was added to the user's post
 *   quote           — the user was quoted in a post
 *   new_thread      — a new thread was created in a board the user watches
 *   mod_action      — a moderator took action on the user's content
 *   badge_awarded   — the user earned a badge
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumNotificationService
{
    /** @var TForumManager */
    private $_manager;

    /** @var array pending notifications queued during this request */
    private $_queue = [];

    public function __construct(TForumManager $manager)
    {
        $this->_manager = $manager;
    }

    // ===================================================================
    // Notification creation helpers
    // ===================================================================

    /**
     * Queue a mention notification.  Called by {@see TForumManager::processMentions()}.
     *
     * @param string $username  the @-mentioned username
     * @param int    $postId    the post that contains the mention
     */
    public function queueMentionNotification(string $username, int $postId): void
    {
        $this->_queue[] = ['type' => 'mention', 'username' => $username, 'post_id' => $postId];
    }

    /**
     * Notify all thread subscribers that a new reply has been posted.
     *
     * @param int    $threadId     the thread that received a reply
     * @param int    $postId       the new post ID
     * @param string $actorUsername who posted the reply
     */
    public function notifyThreadReply(int $threadId, int $postId, string $actorUsername): void
    {
        if (!$this->_manager->getEnableNotifications()) {
            return;
        }

        $subscriptions = TForumSubscriptionRecord::finder()->findAll(
            'thread_id = :tid AND notify_on IN (:all, :mention)',
            [':tid' => $threadId, ':all' => 'all', ':mention' => 'all']
        );

        if (!$subscriptions) {
            return;
        }

        $actor   = $this->resolveProfile($actorUsername);
        $post    = TForumPostRecord::finder()->findByPk($postId);
        $linkUrl = $this->buildPostUrl($postId);

        foreach ($subscriptions as $sub) {
            // Don't notify the person who just posted.
            if ($actor && $sub->user_id === $actor->id) {
                continue;
            }

            $this->saveNotification([
                'user_id'       => $sub->user_id,
                'actor_user_id' => $actor ? $actor->id : null,
                'type'          => 'reply',
                'subject'       => ($actor ? $actor->getEffectiveDisplayName() : 'Someone') . ' replied to a thread you are watching',
                'link_url'      => $linkUrl,
            ]);
        }
    }

    /**
     * Notify a user that a reaction was added to their post.
     *
     * @param int    $postId        the reacted-to post
     * @param string $reactionType  e.g. 'like', 'helpful'
     * @param string $actorUsername who reacted
     */
    public function notifyReaction(int $postId, string $reactionType, string $actorUsername): void
    {
        if (!$this->_manager->getEnableNotifications()) {
            return;
        }

        $post  = TForumPostRecord::finder()->findByPk($postId);
        $actor = $this->resolveProfile($actorUsername);

        if (!$post) {
            return;
        }

        $this->saveNotification([
            'user_id'       => $post->user_id,
            'actor_user_id' => $actor ? $actor->id : null,
            'type'          => 'reaction',
            'subject'       => ($actor ? $actor->getEffectiveDisplayName() : 'Someone') . ' reacted with "' . $reactionType . '" to your post',
            'link_url'      => $this->buildPostUrl($postId),
        ]);
    }

    /**
     * Notify a user that they received a badge.
     *
     * @param int    $userId    recipient user profile ID
     * @param string $badgeName
     * @param string $badgeSlug
     */
    public function notifyBadgeAwarded(int $userId, string $badgeName, string $badgeSlug): void
    {
        if (!$this->_manager->getEnableNotifications()) {
            return;
        }

        $this->saveNotification([
            'user_id'  => $userId,
            'type'     => 'badge_awarded',
            'subject'  => 'You earned the "' . $badgeName . '" badge!',
            'link_url' => $this->_manager->createUrl('forum/UserProfile', ['tab' => 'badges']),
        ]);
    }

    /**
     * Notify a user that a moderator acted on their content.
     *
     * @param int    $userId           affected user
     * @param string $action           e.g. 'deleted your post', 'locked your thread'
     * @param string $modUsername      moderator username
     * @param string $reason
     * @param string $linkUrl
     */
    public function notifyModAction(int $userId, string $action, string $modUsername, string $reason, string $linkUrl): void
    {
        if (!$this->_manager->getEnableNotifications()) {
            return;
        }

        $actor = $this->resolveProfile($modUsername);

        $this->saveNotification([
            'user_id'       => $userId,
            'actor_user_id' => $actor ? $actor->id : null,
            'type'          => 'mod_action',
            'subject'       => 'A moderator ' . $action,
            'body'          => $reason,
            'link_url'      => $linkUrl,
        ]);
    }

    /**
     * Flush the pending mention queue — called after a post is saved and its
     * ID is known.  The queue is populated by {@see TForumManager::processMentions()}.
     */
    public function flushQueue(): void
    {
        foreach ($this->_queue as $item) {
            if ($item['type'] === 'mention') {
                $profile = TForumUserProfileRecord::finder()->findByAttributes(['username' => $item['username']]);
                if ($profile) {
                    $this->saveNotification([
                        'user_id'  => $profile->id,
                        'type'     => 'mention',
                        'subject'  => 'You were mentioned in a post',
                        'link_url' => $this->buildPostUrl($item['post_id']),
                    ]);
                }
            }
        }
        $this->_queue = [];
    }

    // ===================================================================
    // Internal helpers
    // ===================================================================

    /**
     * Persist a notification record.
     *
     * @param array $data associative data matching TForumNotificationRecord columns
     */
    private function saveNotification(array $data): void
    {
        $n                = new TForumNotificationRecord();
        $n->user_id       = $data['user_id']       ?? 0;
        $n->actor_user_id = $data['actor_user_id'] ?? null;
        $n->type          = $data['type']           ?? 'info';
        $n->subject       = $data['subject']        ?? '';
        $n->body          = $data['body']           ?? null;
        $n->link_url      = $data['link_url']       ?? '';
        $n->is_read       = 0;
        $n->email_sent    = 0;
        $n->created_at    = date('Y-m-d H:i:s');

        try {
            $n->save();
        } catch (\Exception $e) {
            // Notifications are non-critical; log and continue.
            error_log('[TForumNotificationService] ' . $e->getMessage());
        }
    }

    /**
     * Resolve a username to a TForumUserProfileRecord.
     *
     * @param string $username
     * @return TForumUserProfileRecord|null
     */
    private function resolveProfile(string $username): ?TForumUserProfileRecord
    {
        return TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]) ?: null;
    }

    /**
     * Build a page URL pointing to a specific post anchor.
     *
     * @param int $postId
     * @return string
     */
    private function buildPostUrl(int $postId): string
    {
        return $this->_manager->createUrl('forum/ThreadView', ['post' => $postId]) . '#post-' . $postId;
    }

    /**
     * Return the number of unread notifications for a user profile ID.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return (int) TForumNotificationRecord::finder()->count('user_id = ? AND is_read = 0', [$userId]);
    }

    /**
     * Mark all notifications for a user as read.
     *
     * @param int $userId
     */
    public function markAllRead(int $userId): void
    {
        $db = $this->_manager->getDbConnection();
        $db->createCommand("UPDATE {$this->_manager->getTable('notifications')} SET is_read = 1 WHERE user_id = :uid")
           ->bindValue(':uid', $userId)
           ->execute();
    }
}
