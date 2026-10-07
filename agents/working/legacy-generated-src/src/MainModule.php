<?php

/**
 * MainModule class file
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/pradosoft/prado-composer-extension
 * @license https://github.com/pradosoft/prado-composer-extension/blob/master/LICENSE
 */

namespace PradoComposerExtension;

use Prado\TPropertyValue;
use Prado\Util\TPluginModule;

/**
 * MainModule class.
 *
 * main example bootstrap module class
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class MainModule extends TPluginModule
{
	/** @var null|string property A */
	private $_propertya;

	/**
	 * Initializes the module, call the parent:init.
	 * @param null|array|\Prado\Xml\TXmlElement $config
	 */
	public function init($config)
	{
		parent::init($config);
	}

	/**
	 * @return null|string gets the Property A from the module
	 */
	public function getPropertyA()
	{
		return $this->_propertya;
	}

	/**
	 * @param null|string $v sets the Property A from the module
	 */
	public function setPropertyA($v)
	{
		$this->_propertya = TPropertyValue::ensureString($v);
	}
}
