<?php

/**
 * BEForumAttachmentDownload class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumAttachmentDownload class.
 *
 * BEForumAttachmentDownload streams the attachment named by the `attachment`
 * request parameter to the browser and ends the request.  Access follows the
 * visibility of the post the file belongs to.  Images are sent inline unless
 * {@see setForceDownload} is enabled.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumAttachmentDownload />
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumAttachmentDownload extends BEForumControl
{
	/**
	 * @return bool whether every file is sent as a download (never inline)
	 */
	public function getForceDownload(): bool
	{
		return (bool) $this->getViewState('ForceDownload', false);
	}

	/**
	 * @param bool $force whether every file is sent as a download
	 */
	public function setForceDownload($force): void
	{
		$this->setViewState('ForceDownload', TPropertyValue::ensureBoolean($force), false);
	}

	/**
	 * @return int the attachment id, read from the `attachment` request parameter when unset
	 */
	public function getAttachmentID(): int
	{
		$id = (int) $this->getViewState('AttachmentID', 0);
		return $id > 0 ? $id : $this->getRequestInt(BEForumUrlBuilder::PARAM_ATTACHMENT, 0);
	}

	/**
	 * @param int $id the attachment id
	 */
	public function setAttachmentID($id): void
	{
		$this->setViewState('AttachmentID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * Sends the file.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$attachments = $this->getForum()->getAttachments();
		$attachment = $attachments->getAttachment($this->getAttachmentID());
		$attachments->sendFile($attachment, $this->getForceDownload());
		$this->endRequest();
	}
}
