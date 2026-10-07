<?php

/**
 * TForumReactionRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumReactionRecord represents a single emoji/like reaction on a post.
 *
 * A user may leave at most one reaction per post, but may change the
 * reaction type (emoji) at any time.  Reactions also contribute to the
 * poster's reputation score when ReputationEnabled is true.
 *
 * Supported reaction types (stored as strings): 'like', 'love', 'laugh',
 * 'wow', 'sad', 'angry', 'helpful', 'insightful'.
 *
 * @property int    $id
 * @property int    $post_id
 * @property int    $user_id     the user giving the reaction
 * @property string $type        reaction type slug, e.g. 'like', 'helpful'
 * @property string $created_at
 * @property string $updated_at
 *
 * Relations:
 * @property TForumPostRecord         $post
 * @property TForumUserProfileRecord  $user
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumReactionRecord extends TActiveRecord
{
    const TABLE = 'forum_reactions';

    /** Recognised reaction type slugs. */
    const TYPES = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'helpful', 'insightful'];

    public $id;
    public $post_id;
    public $user_id;
    public $type       = 'like';
    public $created_at;
    public $updated_at;

    public static $RELATIONS = [
        'post' => [self::BELONGS_TO, TForumPostRecord::class,        'post_id'],
        'user' => [self::BELONGS_TO, TForumUserProfileRecord::class, 'user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
