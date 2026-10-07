<?php

/**
 * TForumAttachmentRecord class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\ActiveRecord;

use Prado\Data\ActiveRecord\TActiveRecord;

/**
 * TForumAttachmentRecord represents a file attached to a post or thread.
 *
 * Attachments are stored on the server filesystem; this record stores the
 * metadata.  Both post-level and thread-level (cover image) attachments
 * are supported — exactly one of `post_id` / `thread_id` will be set.
 *
 * @property int         $id
 * @property int|null    $post_id
 * @property int|null    $thread_id
 * @property int         $user_id
 * @property string      $filename      original file name as uploaded
 * @property string      $stored_name   name on disk (UUID-based)
 * @property string      $mime_type
 * @property int         $file_size     in bytes
 * @property int         $download_count
 * @property int         $is_image      1 when inline-display is appropriate
 * @property string|null $thumbnail_url
 * @property string      $created_at
 *
 * Relations:
 * @property TForumPostRecord|null    $post
 * @property TForumThreadRecord|null  $thread
 * @property TForumUserProfileRecord  $uploader
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumAttachmentRecord extends TActiveRecord
{
    const TABLE = 'forum_attachments';

    public $id;
    public $post_id;
    public $thread_id;
    public $user_id;
    public $filename;
    public $stored_name;
    public $mime_type;
    public $file_size      = 0;
    public $download_count = 0;
    public $is_image       = 0;
    public $thumbnail_url;
    public $created_at;

    public static $RELATIONS = [
        'post'     => [self::BELONGS_TO, TForumPostRecord::class,        'post_id'],
        'thread'   => [self::BELONGS_TO, TForumThreadRecord::class,      'thread_id'],
        'uploader' => [self::BELONGS_TO, TForumUserProfileRecord::class, 'user_id'],
    ];

    public static function finder(string $className = __CLASS__): TActiveRecord
    {
        return parent::finder($className);
    }
}
