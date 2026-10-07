<?php

/**
 * BEForumCommandEventParameter class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Prado\TEventParameter;

/**
 * BEForumCommandEventParameter class.
 *
 * BEForumCommandEventParameter carries a command raised inside a post
 * (quote, reply, ...) from {@see BEForumPostList} to the containing
 * {@see BEForumThreadView} through the `OnPostCommand` event.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumCommandEventParameter extends TEventParameter
{
	/** @var string the command name */
	private string $_name;

	/** @var int the post id */
	private int $_postId;

	/** @var mixed extra command data */
	private $_data;

	/**
	 * @param string $name the command name
	 * @param int $postId the post id
	 * @param mixed $data extra command data
	 */
	public function __construct(string $name, int $postId, $data = null)
	{
		$this->_name = $name;
		$this->_postId = $postId;
		$this->_data = $data;
		parent::__construct();
	}

	/**
	 * @return string the command name
	 */
	public function getName(): string
	{
		return $this->_name;
	}

	/**
	 * @return int the post id
	 */
	public function getPostID(): int
	{
		return $this->_postId;
	}

	/**
	 * @return mixed extra command data
	 */
	public function getData()
	{
		return $this->_data;
	}
}
