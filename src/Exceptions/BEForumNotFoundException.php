<?php

/**
 * BEForumNotFoundException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Exceptions;

use Prado\Exceptions\THttpException;

/**
 * BEForumNotFoundException class.
 *
 * BEForumNotFoundException is an HTTP 404 exception raised when a forum entity
 * (category, board, thread, post, member, tag, ...) cannot be found or has been
 * removed.  The error code is a `forum_*` key from `src/errorMessages.txt`.
 *
 * ```php
 * throw new BEForumNotFoundException('forum_thread_not_found', $threadId);
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumNotFoundException extends THttpException
{
	/**
	 * @param string $errorMessage the error message key
	 * @param mixed ...$args the message placeholders
	 */
	public function __construct($errorMessage, ...$args)
	{
		parent::__construct(404, $errorMessage, ...$args);
	}

	/**
	 * @return string the path of the forum error message file
	 */
	protected function getErrorMessageFile()
	{
		return BEForumException::getForumMessageFile();
	}
}
