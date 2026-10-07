<?php

/**
 * TForumNotificationRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumNotificationRecord stores on-site and pending-email notifications.
 *
 * Notification types: 'reply', 'mention', 'reaction', 'quote', 'new_thread',
 * 'mod_action', 'badge_awarded', 'subscription_post'.
 *
 * @property int         $id
 * @property int         $user_id         recipient
 * @property int|null    $actor_user_id   who triggered the notification
 * @property string      $type
 * @property string      $subject         short human-readable subject line
 * @property string|null $body            longer notification text (optional)
 * @property string      $link_url        deep-link into the relevant page
 * @property int         $is_read
 * @property int         $email_sent      1 when an email has been dispatched
 * @property string|null $email_sent_at
 * @property string      $created_at
 *
 * Relations:
 * @property TForumUserProfileRecord       $recipient
 * @property TForumUserProfileRecord|null  $actor
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumNotificationRecord extends TActiveRecord
{
    const TABLE = 'forum_notifications';

    public $id;
    public $user_id;
    public $actor_user_id;
    public $type;
    public $subject;
    public $body;
    public $link_url;
    public $is_read       = 0;
    public $email_sent    = 0;
    public $email_sent_at;
    public $created_at;

    public static $RELATIONS = [
        'recipient' => [self::BELONGS_TO, TForumUserProfileRecord::class, 'user_id'],
        'actor'     => [self::BELONGS_TO, TForumUserProfileRecord::class, 'actor_user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
