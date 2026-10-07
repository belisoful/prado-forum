<?php

/**
 * BEForumAttachment class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Data;

/**
 * BEForumAttachment class.
 *
 * BEForumAttachment describes a file uploaded with a {@see BEForumPost}.  The
 * file itself is stored under the module attachment path as {@see $stored_name};
 * {@see $file_name} is the original client file name.
 *
 * @property-read BEForumPost $post the post (lazy)
 * @property-read null|BEForumMember $member the uploading member (lazy)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumAttachment extends BEForumRecord
{
	public const TABLE_NAME = 'attachments';

	public static $RELATIONS = [
		'post' => [self::BELONGS_TO, BEForumPost::class, 'post_id'],
		'member' => [self::BELONGS_TO, BEForumMember::class, 'member_id'],
	];

	/** @var null|int primary key */
	public $id;
	/** @var int the post */
	public $post_id;
	/** @var null|int the uploading member */
	public $member_id;
	/** @var string the original file name */
	public $file_name;
	/** @var string the stored file name */
	public $stored_name;
	/** @var string the MIME type */
	public $mime_type;
	/** @var int the size in bytes */
	public $size = 0;
	/** @var int number of downloads */
	public $download_count = 0;
	/** @var null|string creation time */
	public $created_at;

	/**
	 * @return bool whether the attachment is an image
	 */
	public function getIsImage(): bool
	{
		return str_starts_with(strtolower((string) $this->mime_type), 'image/');
	}

	/**
	 * @return bool whether the attachment is a raster image that is safe to display inline (SVG is excluded because it may carry scripts)
	 */
	public function getIsInlineImage(): bool
	{
		return in_array(strtolower((string) $this->mime_type), ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif'], true);
	}

	/**
	 * @return string the lower cased file extension without dot
	 */
	public function getExtension(): string
	{
		return strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION));
	}
}
