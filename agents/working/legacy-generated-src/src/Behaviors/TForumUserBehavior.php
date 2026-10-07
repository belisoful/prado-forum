<?php

/**
 * TForumUserBehavior class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Behaviors;

use Prado\TBehavior;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * TForumUserBehavior is attached to the application's user manager module
 * by {@see TForumManager::attachUserBehavior()}.
 *
 * It extends the user manager (or any TComponent acting as a user service)
 * with forum-aware methods so that page code and portlets can call, e.g.:
 *
 * ```php
 * $profile  = $this->Application->getModule('users')->getForumProfile();
 * $isBanned = $this->Application->getModule('users')->isForumBanned();
 * $canPost  = $this->Application->getModule('users')->canForumPost();
 * ```
 *
 * The behavior resolves the current user's TForumUserProfileRecord lazily
 * and caches it for the duration of the request.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumUserBehavior extends TBehavior
{
    /** @var TForumManager */
    private $_forumManager;

    /** @var TForumUserProfileRecord|null|false resolved profile; false = not yet loaded */
    private $_profile = false;

    // ===================================================================
    // Wiring
    // ===================================================================

    /**
     * @param TForumManager $manager the core forum manager
     */
    public function setForumManager(TForumManager $manager): void
    {
        $this->_forumManager = $manager;
    }

    /**
     * @return TForumManager
     */
    public function getForumManager(): TForumManager
    {
        return $this->_forumManager;
    }

    // ===================================================================
    // Profile access
    // ===================================================================

    /**
     * Return the TForumUserProfileRecord for the currently authenticated user.
     *
     * If no profile exists yet and the user is authenticated, one is created
     * automatically (lazy provisioning) so new users are seamlessly on-boarded.
     *
     * @return TForumUserProfileRecord|null null when the user is a guest
     */
    public function getForumProfile(): ?TForumUserProfileRecord
    {
        if ($this->_profile !== false) {
            return $this->_profile;
        }

        $user = $this->getOwner()->getUser();
        if (!$user || $user->getIsGuest()) {
            $this->_profile = null;
            return null;
        }

        $username = $user->getName();
        $profile  = TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]);

        if ($profile === null) {
            // Auto-provision a profile for users who have authenticated through
            // the app's own system but haven't posted yet.
            $profile           = new TForumUserProfileRecord();
            $profile->username = $username;
            $profile->email    = method_exists($user, 'getEmail') ? $user->getEmail() : '';
            $profile->created_at = date('Y-m-d H:i:s');
            $profile->updated_at = date('Y-m-d H:i:s');
            try {
                $profile->save();
            } catch (\Exception $e) {
                error_log('[TForumUserBehavior] Profile auto-provision failed: ' . $e->getMessage());
                $this->_profile = null;
                return null;
            }
        }

        // Update last_seen_at on each request (throttled to once per 5 minutes via cache).
        $this->touchLastSeen($profile);

        $this->_profile = $profile;
        return $profile;
    }

    /**
     * Invalidate the cached profile, forcing a re-load on next access.
     * Call this after modifying profile data within the same request.
     */
    public function resetForumProfile(): void
    {
        $this->_profile = false;
    }

    // ===================================================================
    // Convenience predicates
    // ===================================================================

    /**
     * @return bool whether the current user is banned from the forum
     */
    public function isForumBanned(): bool
    {
        $profile = $this->getForumProfile();
        return $profile !== null && $profile->isBanned();
    }

    /**
     * @return bool whether the current user may post (authenticated, not banned,
     *   account active, email verified if required)
     */
    public function canForumPost(): bool
    {
        $fm      = $this->_forumManager;
        $user    = $this->getOwner()->getUser();
        $profile = $this->getForumProfile();

        if ($user === null || $user->getIsGuest()) {
            return $fm->getGuestPosting();
        }

        if ($profile === null || !$profile->is_active) {
            return false;
        }

        if ($profile->isBanned()) {
            return false;
        }

        if ($fm->getRequireEmailVerification() && $profile->email_verified_at === null) {
            return false;
        }

        return true;
    }

    /**
     * @return bool whether the current user has moderator privileges.
     *   Checks for the PRADO role 'ForumModerator' or 'ForumAdmin'.
     */
    public function isForumModerator(): bool
    {
        $user = $this->getOwner()->getUser();
        if ($user === null || $user->getIsGuest()) {
            return false;
        }
        return $user->isInRole('ForumModerator') || $user->isInRole('ForumAdmin');
    }

    /**
     * @return bool whether the current user has admin privileges.
     */
    public function isForumAdmin(): bool
    {
        $user = $this->getOwner()->getUser();
        if ($user === null || $user->getIsGuest()) {
            return false;
        }
        return $user->isInRole('ForumAdmin');
    }

    /**
     * Return the number of unread notifications for the current user.
     *
     * @return int 0 when the user is a guest or has no notifications
     */
    public function getForumUnreadNotifications(): int
    {
        $profile = $this->getForumProfile();
        if ($profile === null) {
            return 0;
        }
        return $this->_forumManager->getNotificationService()->getUnreadCount($profile->id);
    }

    /**
     * Return the forum display name for the current user.
     *
     * @return string display name, or empty string for guests
     */
    public function getForumDisplayName(): string
    {
        $profile = $this->getForumProfile();
        return $profile ? $profile->getEffectiveDisplayName() : '';
    }

    /**
     * Return the avatar URL for the current user, falling back to the
     * default avatar configured on TForumManager.
     *
     * @return string
     */
    public function getForumAvatarUrl(): string
    {
        $profile = $this->getForumProfile();
        if ($profile && $profile->avatar_url) {
            return $profile->avatar_url;
        }
        return $this->_forumManager->getDefaultAvatarUrl();
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    /**
     * Update last_seen_at at most once every 5 minutes to avoid DB hammering.
     */
    private function touchLastSeen(TForumUserProfileRecord $profile): void
    {
        $cache = $this->_forumManager->getCache();
        if ($cache !== null) {
            $key = 'forum_seen_' . md5($profile->username);
            if ($cache->get($key) !== false) {
                return;
            }
            $cache->set($key, 1, 300); // 5 minutes
        }

        try {
            $profile->last_seen_at = date('Y-m-d H:i:s');
            $profile->save();
        } catch (\Exception $e) {
            // Non-critical.
        }
    }
}
