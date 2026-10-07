<?php

/**
 * BEForumValidationException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Exceptions;

/**
 * BEForumValidationException class.
 *
 * BEForumValidationException reports a user input problem, such as an empty
 * post body, a title that is too long or a closed poll.  The message is
 * intended for display to the end user; template controls catch this
 * exception and show {@see getErrorMessage} next to the form.  {@see getField}
 * names the offending input when known.
 *
 * ```php
 * throw new BEForumValidationException('title', 'forum_thread_title_required');
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumValidationException extends BEForumException
{
	/** @var string the name of the field that failed validation */
	private string $_field;

	/**
	 * @param string $field the name of the field that failed validation, empty for the whole form
	 * @param string $errorMessage the error message key
	 * @param mixed ...$args the message placeholders
	 */
	public function __construct(string $field, $errorMessage, ...$args)
	{
		$this->_field = $field;
		parent::__construct($errorMessage, ...$args);
	}

	/**
	 * @return string the name of the field that failed validation
	 */
	public function getField(): string
	{
		return $this->_field;
	}
}
