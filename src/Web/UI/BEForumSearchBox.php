<?php

/**
 * BEForumSearchBox class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Prado\TPropertyValue;

/**
 * BEForumSearchBox class.
 *
 * BEForumSearchBox is a small search form that sends the query to the search
 * page, optionally limited to a board.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumSearchBox BoardID=<%= $this->Request['board'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TTextBox $Query
 * @property \Prado\Web\UI\WebControls\TButton $Go
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSearchBox extends BEForumControl
{
	/**
	 * @return int the board to search in, 0 for the whole forum
	 */
	public function getBoardID(): int
	{
		return (int) $this->getViewState('BoardID', 0);
	}

	/**
	 * @param int $id the board to search in, 0 for the whole forum
	 */
	public function setBoardID($id): void
	{
		$this->setViewState('BoardID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return string the placeholder text
	 */
	public function getPlaceholder(): string
	{
		return (string) $this->getViewState('Placeholder', $this->t('Search the forum'));
	}

	/**
	 * @param string $text the placeholder text
	 */
	public function setPlaceholder($text): void
	{
		$this->setViewState('Placeholder', TPropertyValue::ensureString($text));
	}

	/**
	 * Redirects to the search page.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function searchClicked($sender, $param): void
	{
		$query = trim((string) $this->Query->getText());
		if ($query === '') {
			return;
		}
		$this->redirect($this->getUrls()->search($query, $this->getBoardID() > 0 ? $this->getBoardID() : null));
	}

	/**
	 * Fills the query from the request.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		if (!$this->getPage()->getIsPostBack()) {
			$this->Query->setText($this->getRequestString('q'));
		}
	}
}
