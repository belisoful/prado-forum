<?php

/**
 * BEForumFloodException class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Exceptions;

/**
 * BEForumFloodException class.
 *
 * BEForumFloodException is raised by the flood control when a member posts
 * faster than {@see \Belisoful\Forum\BEForumModule::getFloodInterval} allows.
 * {@see getRetryAfter} tells how many seconds the member must wait.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumFloodException extends BEForumValidationException
{
	/** @var int seconds until the member may post again */
	private int $_retryAfter;

	/**
	 * @param int $retryAfter seconds until the member may post again
	 */
	public function __construct(int $retryAfter)
	{
		$this->_retryAfter = max(1, $retryAfter);
		parent::__construct('', 'forum_flood_control', $this->_retryAfter);
	}

	/**
	 * @return int seconds until the member may post again
	 */
	public function getRetryAfter(): int
	{
		return $this->_retryAfter;
	}
}
