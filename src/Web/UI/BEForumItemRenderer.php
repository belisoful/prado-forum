<?php

/**
 * BEForumItemRenderer class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Prado\Web\UI\WebControls\TRepeaterItemRenderer;

/**
 * BEForumItemRenderer class.
 *
 * BEForumItemRenderer is the base class of the repeater item renderers of the
 * forum (board rows, thread rows, member rows, ...).  A renderer is a
 * templated control instantiated once per data row; its template accesses the
 * row through `$this->Data` and the forum helpers ({@see css}, {@see e},
 * {@see t}, {@see getUrls}, ...) directly, which keeps nested list templates
 * readable.  Rows are view model arrays whose values are already HTML escaped.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
abstract class BEForumItemRenderer extends TRepeaterItemRenderer
{
	use BEForumControlTrait;

	/**
	 * @param string $key a row key
	 * @param mixed $default the default when the key is missing
	 * @return mixed the row value
	 */
	public function item(string $key, $default = null)
	{
		$data = $this->getData();
		return is_array($data) && array_key_exists($key, $data) ? $data[$key] : $default;
	}

	/**
	 * @param string $key a boolean row key
	 * @return bool the row value
	 */
	public function is(string $key): bool
	{
		return (bool) $this->item($key, false);
	}

	/**
	 * Binds a child repeater to a row key holding rows.
	 * @param string $id the repeater id
	 * @param string $key the row key
	 */
	protected function bindChildRepeater(string $id, string $key): void
	{
		$repeater = $this->findControl($id);
		$rows = $this->item($key, []);
		if ($repeater !== null) {
			$repeater->setDataSource(is_array($rows) ? $rows : []);
			$repeater->dataBind();
			$repeater->setVisible(is_array($rows) && count($rows) > 0);
		}
	}

	/**
	 * Binds child repeaters after the row has been assigned.
	 * @param mixed $param the event parameter
	 */
	public function onDataBinding($param)
	{
		parent::onDataBinding($param);
		$this->bindChildRepeaters();
	}

	/**
	 * Hook for renderers with nested repeaters.
	 */
	protected function bindChildRepeaters(): void
	{
	}
}
