<?php

/**
 * TForumTagRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumTagRecord represents a row in the `forum_tags` table.
 *
 * Tags are short labels users attach to threads for categorisation and
 * discovery.  The `slug` field is the URL-safe form of the name.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug         URL-safe lower-case form
 * @property int    $thread_count number of threads tagged with this tag
 * @property string $created_at
 *
 * Relations:
 * @property TForumThreadRecord[] $threads
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumTagRecord extends TActiveRecord
{
    const TABLE = 'forum_tags';

    public $id;
    public $name;
    public $slug;
    public $thread_count = 0;
    public $created_at;

    public static $RELATIONS = [
        'threads' => [self::MANY_TO_MANY, TForumThreadRecord::class, 'forum_thread_tags(tag_id, thread_id)'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }

    /**
     * Generate a URL-safe slug from the tag name.
     *
     * @param string $name raw tag name
     * @return string slug
     */
    public static function slugify(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        return trim($name, '-');
    }
}
