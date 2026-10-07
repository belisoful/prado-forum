<?php

/**
 * TForumBadgeRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumBadgeRecord defines a badge/achievement that can be awarded to users.
 *
 * Badges are awarded automatically by the notification/reputation system or
 * manually by administrators.  The `criteria_json` field holds the machine-
 * readable conditions that trigger auto-award (e.g. post count thresholds).
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string|null $icon_url
 * @property string      $slug              machine-readable identifier
 * @property int         $is_active
 * @property string|null $criteria_json     auto-award rules
 * @property int         $reputation_bonus  points awarded alongside the badge
 * @property string      $created_at
 *
 * Relations:
 * @property TForumUserProfileRecord[] $users
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumBadgeRecord extends TActiveRecord
{
    const TABLE = 'forum_badges';

    public $id;
    public $name;
    public $description;
    public $icon_url;
    public $slug;
    public $is_active       = 1;
    public $criteria_json;
    public $reputation_bonus = 0;
    public $created_at;

    public static $RELATIONS = [
        'users' => [self::MANY_TO_MANY, TForumUserProfileRecord::class, 'forum_user_badges(badge_id, user_id)'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
