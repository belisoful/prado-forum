<?php

/**
 * TForumUserProfileRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumUserProfileRecord stores forum-specific user data.
 *
 * This is intentionally separate from the application's own user/auth table.
 * The `username` column mirrors the identity used by the PRADO user manager,
 * allowing the forum to link to the auth system without coupling schemas.
 *
 * @property int         $id
 * @property string      $username          matches the app's TUser getName()
 * @property string|null $display_name      overrideable display name
 * @property string|null $email
 * @property string|null $avatar_url
 * @property string|null $signature
 * @property string|null $location
 * @property string|null $website
 * @property string|null $bio
 * @property int         $post_count
 * @property int         $thread_count
 * @property int         $reputation_points
 * @property int         $is_active
 * @property int         $is_banned
 * @property string|null $banned_reason
 * @property string|null $banned_until      NULL = permanent ban
 * @property int         $warn_level        0–100 warning percentage
 * @property string|null $last_seen_at
 * @property string|null $last_post_at
 * @property string|null $email_verified_at
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Relations:
 * @property TForumThreadRecord[]  $threads
 * @property TForumPostRecord[]    $posts
 * @property TForumBadgeRecord[]   $badges
 * @property TForumSubscriptionRecord[] $subscriptions
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumUserProfileRecord extends TActiveRecord
{
    const TABLE = 'forum_user_profiles';

    public $id;
    public $username;
    public $display_name;
    public $email;
    public $avatar_url;
    public $signature;
    public $location;
    public $website;
    public $bio;
    public $post_count       = 0;
    public $thread_count     = 0;
    public $reputation_points = 0;
    public $is_active        = 1;
    public $is_banned        = 0;
    public $banned_reason;
    public $banned_until;
    public $warn_level       = 0;
    public $last_seen_at;
    public $last_post_at;
    public $email_verified_at;
    public $created_at;
    public $updated_at;

    public static $RELATIONS = [
        'threads'       => [self::HAS_MANY, TForumThreadRecord::class,      'user_id'],
        'posts'         => [self::HAS_MANY, TForumPostRecord::class,        'user_id'],
        'badges'        => [self::MANY_TO_MANY, TForumBadgeRecord::class,   'forum_user_badges(user_id, badge_id)'],
        'subscriptions' => [self::HAS_MANY, TForumSubscriptionRecord::class, 'user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }

    /**
     * Return whether this profile is currently banned (and ban has not expired).
     */
    public function isBanned(): bool
    {
        if (!$this->is_banned) {
            return false;
        }
        if ($this->banned_until === null) {
            return true; // permanent
        }
        return strtotime($this->banned_until) > time();
    }

    /**
     * Return the best available display name for this user.
     */
    public function getEffectiveDisplayName(): string
    {
        return $this->display_name ?: $this->username;
    }
}
