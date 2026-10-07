<?php

/**
 * BEForumConfigurationException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Exceptions;

use Prado\Exceptions\TConfigurationException;

/**
 * BEForumConfigurationException class.
 *
 * BEForumConfigurationException is thrown when the forum module or one of its
 * components has been configured with invalid property values.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumConfigurationException extends TConfigurationException
{
	/**
	 * @return string the path of the forum error message file
	 */
	protected function getErrorMessageFile()
	{
		return BEForumException::getForumMessageFile();
	}
}
