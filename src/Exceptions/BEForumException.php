<?php

/**
 * BEForumException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Exceptions;

use Prado\Exceptions\TApplicationException;

/**
 * BEForumException class.
 *
 * BEForumException is the base exception of the forum extension.  Error codes are
 * looked up in `src/errorMessages.txt` (all keys are prefixed with `forum_`) so
 * the message file resolves even when the {@see \Belisoful\Forum\BEForumModule} has
 * not registered it globally, for example inside unit tests.
 *
 * ```php
 * throw new BEForumException('forum_board_not_found', $boardId);
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumException extends TApplicationException
{
	/**
	 * @return string the path of the forum error message file
	 */
	protected function getErrorMessageFile()
	{
		return BEForumException::getForumMessageFile();
	}

	/**
	 * @return string the path of the forum error message file
	 */
	public static function getForumMessageFile(): string
	{
		return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'errorMessages.txt';
	}
}
