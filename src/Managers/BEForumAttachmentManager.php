<?php

/**
 * BEForumAttachmentManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumAttachment;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Prado\Web\UI\WebControls\TFileUploadItem;

/**
 * BEForumAttachmentManager class.
 *
 * BEForumAttachmentManager stores uploaded files next to posts.  Files are
 * validated against the module's allowed extensions and maximum size
 * (`dyAllowedAttachment` may override the decision), stored under the module
 * attachment path with an unguessable name and streamed back through
 * {@see sendFile}.
 *
 * ```php
 * foreach ($this->Upload->getFiles() as $item) {
 *     $forum->getAttachments()->attachUploadedItem($post, $item);
 * }
 * ```
 *
 * @method bool dyAllowedAttachment(bool $allowed, string $fileName, string $mimeType, int $size, BEForumPost $post)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumAttachmentManager extends BEForumManager
{
	/** The MIME type stored when the content type of a file cannot be detected */
	public const MIME_UNKNOWN = 'application/octet-stream';

	/**
	 * @throws BEForumConfigurationException when the directory cannot be created or written
	 * @return string the storage directory, created when missing
	 */
	public function getStoragePath(): string
	{
		$path = $this->getModule()->getAttachmentPath();
		if (!is_dir($path) && !@mkdir($path, 0o775, true) && !is_dir($path)) {
			throw new BEForumConfigurationException('forum_attachment_path_invalid', $path);
		}
		if (!is_writable($path)) {
			throw new BEForumConfigurationException('forum_attachment_path_invalid', $path);
		}
		return $path;
	}

	/**
	 * @param BEForumAttachment $attachment the attachment
	 * @return string the absolute path of the stored file
	 */
	public function getFilePath(BEForumAttachment $attachment): string
	{
		return $this->getModule()->getAttachmentPath() . DIRECTORY_SEPARATOR . basename((string) $attachment->stored_name);
	}

	/**
	 * Validates a file against the module rules.
	 * @param string $fileName the client file name
	 * @param string $mimeType the MIME type
	 * @param int $size the size in bytes
	 * @param BEForumPost $post the post
	 * @throws BEForumValidationException when the file is not allowed
	 */
	public function validateFile(string $fileName, string $mimeType, int $size, BEForumPost $post): void
	{
		$module = $this->getModule();
		if (!$module->getEnableAttachments()) {
			throw new BEForumValidationException('attachment', 'forum_attachments_disabled');
		}
		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$allowed = $extension !== '' && in_array($extension, $module->getAttachmentTypes(), true) && $size > 0 && $size <= $module->getAttachmentMaxSize();
		$allowed = (bool) $this->dyAllowedAttachment($allowed, $fileName, $mimeType, $size, $post);
		if (!$allowed) {
			if ($size > $module->getAttachmentMaxSize()) {
				throw new BEForumValidationException('attachment', 'forum_attachment_too_large', $fileName, $module->getAttachmentMaxSize());
			}
			throw new BEForumValidationException('attachment', 'forum_attachment_type_invalid', $fileName, implode(', ', $module->getAttachmentTypes()));
		}
	}

	/**
	 * Stores a file from a path and attaches it to a post.
	 * @param BEForumPost $post the post
	 * @param string $fileName the client file name
	 * @param string $sourcePath the path of the file to store
	 * @param null|string $mimeType the MIME type claimed by the client; the stored
	 *   type is always detected from the file content, the claim is only used when
	 *   detection is not available
	 * @param bool $move whether to move (true) or copy (false) the source
	 * @throws BEForumValidationException when the file is not allowed
	 * @throws BEForumForbiddenException when the post may not be edited by the current user
	 * @throws BEForumConfigurationException when the file cannot be stored
	 * @return BEForumAttachment the attachment
	 */
	public function attachFile(BEForumPost $post, string $fileName, string $sourcePath, ?string $mimeType = null, bool $move = true): BEForumAttachment
	{
		$module = $this->getModule();
		$this->authorize(BEForumPermissions::ATTACH, $this->extraFor((int) $post->board_id));
		if (!$module->getPosts()->canEdit($post)) {
			throw new BEForumForbiddenException(BEForumPermissions::ATTACH, 'forum_attachment_forbidden');
		}
		if (!is_file($sourcePath)) {
			throw new BEForumValidationException('attachment', 'forum_attachment_missing', $fileName);
		}
		$size = (int) filesize($sourcePath);
		$detected = $this->detectMimeType($sourcePath);
		$mimeType = $detected !== self::MIME_UNKNOWN || !$mimeType ? $detected : $mimeType;
		$fileName = basename(str_replace('\\', '/', $fileName));
		$this->validateFile($fileName, $mimeType, $size, $post);
		$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$storedName = $post->getId() . '-' . bin2hex(random_bytes(12)) . ($extension !== '' ? '.' . $extension : '');
		$target = $this->getStoragePath() . DIRECTORY_SEPARATOR . $storedName;
		$ok = $move ? @rename($sourcePath, $target) : @copy($sourcePath, $target);
		if (!$ok && $move) {
			$ok = @copy($sourcePath, $target) && @unlink($sourcePath);
		}
		if (!$ok) {
			throw new BEForumConfigurationException('forum_attachment_store_failed', $target);
		}
		@chmod($target, 0o664);
		$attachment = new BEForumAttachment();
		$attachment->post_id = $post->getId();
		$attachment->member_id = $this->getMember()?->getId();
		$attachment->file_name = mb_substr($fileName, 0, 255);
		$attachment->stored_name = $storedName;
		$attachment->mime_type = mb_substr($mimeType, 0, 128);
		$attachment->size = $size;
		$attachment->save();
		$this->raise('onAttachmentAdded', $attachment, ['post' => $post]);
		return $attachment;
	}

	/**
	 * Stores a PRADO upload item.
	 * @param BEForumPost $post the post
	 * @param TFileUploadItem $item the upload item
	 * @throws BEForumValidationException when the upload failed or is not allowed
	 * @return BEForumAttachment the attachment
	 */
	public function attachUploadedItem(BEForumPost $post, TFileUploadItem $item): BEForumAttachment
	{
		if (!$item->getHasFile() || $item->getErrorCode() !== UPLOAD_ERR_OK) {
			throw new BEForumValidationException('attachment', 'forum_attachment_upload_failed', (string) $item->getFileName());
		}
		return $this->attachFile($post, (string) $item->getFileName(), (string) $item->getLocalName(), (string) $item->getFileType(), true);
	}

	/**
	 * @param string $path a file path
	 * @return string the detected MIME type, `application/octet-stream` when unknown
	 */
	public function detectMimeType(string $path): string
	{
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo) {
				$type = finfo_file($finfo, $path);
				finfo_close($finfo);
				if (is_string($type) && $type !== '') {
					return $type;
				}
			}
		}
		if (function_exists('mime_content_type')) {
			$type = @mime_content_type($path);
			if (is_string($type) && $type !== '') {
				return $type;
			}
		}
		return self::MIME_UNKNOWN;
	}

	/**
	 * @param int $id the attachment id
	 * @throws BEForumNotFoundException when the attachment does not exist
	 * @return BEForumAttachment the attachment, only when its post is visible
	 */
	public function getAttachment(int $id): BEForumAttachment
	{
		$this->getDbConnection();
		$attachment = BEForumAttachment::findOne($id);
		if ($attachment === null) {
			throw new BEForumNotFoundException('forum_attachment_not_found', $id);
		}
		$this->getModule()->getPosts()->getPost((int) $attachment->post_id);
		return $attachment;
	}

	/**
	 * @param BEForumPost $post the post
	 * @return BEForumAttachment[] the attachments of the post
	 */
	public function getAttachments(BEForumPost $post): array
	{
		return $this->getAttachmentsForPosts([(int) $post->getId()])[(int) $post->getId()] ?? [];
	}

	/**
	 * Loads the attachments of several posts in one query.
	 * @param int[] $postIds the post ids
	 * @return array<int, BEForumAttachment[]> the attachments keyed by post id
	 */
	public function getAttachmentsForPosts(array $postIds): array
	{
		$postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
		if (!$postIds || !$this->getModule()->getEnableAttachments()) {
			return [];
		}
		$this->getDbConnection();
		$result = [];
		foreach (BEForumAttachment::finder()->findAll(BEForumAttachment::criteria($this->inCondition('post_id', $postIds), [], ['created_at' => 'asc', 'id' => 'asc'])) as $attachment) {
			$result[(int) $attachment->post_id][] = $attachment;
		}
		return $result;
	}

	/**
	 * Deletes an attachment and its file.
	 * @param BEForumAttachment $attachment the attachment
	 * @param bool $authorize whether to check the post edit permission
	 * @return bool whether the file was removed
	 */
	public function removeAttachment(BEForumAttachment $attachment, bool $authorize = true): bool
	{
		if ($authorize) {
			$post = $this->getModule()->getPosts()->findPost((int) $attachment->post_id);
			if ($post !== null && !$this->getModule()->getPosts()->canEdit($post)) {
				$this->authorize(BEForumPermissions::MODERATE, $this->extraFor((int) $post->board_id));
			}
		}
		$path = $this->getFilePath($attachment);
		$removed = is_file($path) && @unlink($path);
		$attachment->delete();
		return $removed;
	}

	/**
	 * Streams an attachment to the client and counts the download.
	 * @param BEForumAttachment $attachment the attachment
	 * @param bool $forceDownload whether to send as attachment rather than inline
	 * @throws BEForumNotFoundException when the file is missing
	 */
	public function sendFile(BEForumAttachment $attachment, bool $forceDownload = true): void
	{
		$path = $this->getFilePath($attachment);
		if (!is_file($path)) {
			throw new BEForumNotFoundException('forum_attachment_missing', (string) $attachment->file_name);
		}
		BEForumAttachment::execute('UPDATE {table} SET download_count = download_count + 1 WHERE id = :id', ['id' => $attachment->getId()]);
		$app = $this->getModule()->getApplication();
		$response = $app ? $app->getResponse() : null;
		if ($response === null) {
			return;
		}
		$response->appendHeader('X-Content-Type-Options: nosniff');
		$response->writeFile($path, null, (string) $attachment->mime_type, null, $forceDownload || !$attachment->getIsInlineImage(), (string) $attachment->file_name, (int) $attachment->size);
	}

	/**
	 * @param int $bytes a size in bytes
	 * @return string a human readable size
	 */
	public static function formatSize(int $bytes): string
	{
		$units = ['B', 'KB', 'MB', 'GB'];
		$value = (float) max(0, $bytes);
		$unit = 0;
		while ($value >= 1024 && $unit < count($units) - 1) {
			$value /= 1024;
			$unit++;
		}
		return ($unit === 0 ? (string) (int) $value : number_format($value, 1)) . ' ' . $units[$unit];
	}
}
