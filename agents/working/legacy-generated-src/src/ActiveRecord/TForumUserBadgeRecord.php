<?php

/**
 * TForumUserBadgeRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumUserBadgeRecord is the join table between users and badges.
 *
 * It is also the explicit pivot model so we can store extra metadata such as
 * the granting moderator and the context that triggered the award.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $badge_id
 * @property int|null    $granted_by_user_id   NULL = auto-awarded by system
 * @property string|null $context              human-readable award reason
 * @property string      $awarded_at
 *
 * Relations:
 * @property TForumUserProfileRecord  $user
 * @property TForumBadgeRecord        $badge
 * @property TForumUserProfileRecord|null $grantedBy
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumUserBadgeRecord extends TActiveRecord
{
    const TABLE = 'forum_user_badges';

    public $id;
    public $user_id;
    public $badge_id;
    public $granted_by_user_id;
    public $context;
    public $awarded_at;

    public static $RELATIONS = [
        'user'      => [self::BELONGS_TO, TForumUserProfileRecord::class, 'user_id'],
        'badge'     => [self::BELONGS_TO, TForumBadgeRecord::class,       'badge_id'],
        'grantedBy' => [self::BELONGS_TO, TForumUserProfileRecord::class, 'granted_by_user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
