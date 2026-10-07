<?php

/**
 * BEForumEventParameter class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumRecord;
use Prado\TEventParameter;

/**
 * BEForumEventParameter class.
 *
 * BEForumEventParameter is passed to every `on*` event raised by
 * {@see BEForumModule}.  It carries the {@see getRecord record} the event is
 * about (thread, post, member, ...), the {@see getActor acting member} and a
 * bag of {@see getData extra data} such as the previous values of an update
 * or the reason of a moderation action.
 *
 * ```php
 * $forum->onPostCreated[] = function ($sender, BEForumEventParameter $param) {
 *     $post = $param->getRecord();
 *     ...
 * };
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumEventParameter extends TEventParameter
{
	/** @var null|BEForumRecord the subject record */
	private ?BEForumRecord $_record;

	/** @var null|BEForumMember the acting member */
	private ?BEForumMember $_actor;

	/** @var array extra data */
	private array $_data;

	/**
	 * @param null|BEForumRecord $record the subject record
	 * @param null|BEForumMember $actor the acting member
	 * @param array $data extra data
	 */
	public function __construct(?BEForumRecord $record = null, ?BEForumMember $actor = null, array $data = [])
	{
		$this->_record = $record;
		$this->_actor = $actor;
		$this->_data = $data;
		parent::__construct();
	}

	/**
	 * @return null|BEForumRecord the subject record
	 */
	public function getRecord(): ?BEForumRecord
	{
		return $this->_record;
	}

	/**
	 * @return null|BEForumMember the acting member
	 */
	public function getActor(): ?BEForumMember
	{
		return $this->_actor;
	}

	/**
	 * @return array extra data
	 */
	public function getData(): array
	{
		return $this->_data;
	}

	/**
	 * @param string $key the data key
	 * @param mixed $default the value when unset
	 * @return mixed the data value
	 */
	public function getDataItem(string $key, $default = null)
	{
		return $this->_data[$key] ?? $default;
	}

	/**
	 * @param string $key the data key
	 * @param mixed $value the value
	 */
	public function setDataItem(string $key, $value): void
	{
		$this->_data[$key] = $value;
	}
}
