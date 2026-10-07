<?php

/**
 * BEForumBoardHeader class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumBoardHeader class.
 *
 * BEForumBoardHeader shows a board: its name, description, sub boards,
 * moderators, a subscribe button and a "mark all read" action.  It also sets
 * the page title.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumBoardHeader BoardID=<%= $this->Request['board'] %> />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $SubBoards
 * @property \Belisoful\Forum\Web\UI\BEForumSubscribeButton $Subscribe
 * @property \Prado\Web\UI\WebControls\TLinkButton $MarkRead
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBoardHeader extends BEForumControl
{
	/** @var null|BEForumBoard the board */
	private ?BEForumBoard $_board = null;

	/**
	 * @return int the board id, read from the `board` request parameter when unset
	 */
	public function getBoardID(): int
	{
		$id = (int) $this->getViewState('BoardID', 0);
		return $id > 0 ? $id : $this->getRequestInt(BEForumUrlBuilder::PARAM_BOARD, 0);
	}

	/**
	 * @param int $id the board id
	 */
	public function setBoardID($id): void
	{
		$this->setViewState('BoardID', TPropertyValue::ensureInteger($id), 0);
		$this->_board = null;
	}

	/**
	 * @return bool whether the page title is set from the board name
	 */
	public function getSetsPageTitle(): bool
	{
		return (bool) $this->getViewState('SetsPageTitle', true);
	}

	/**
	 * @param bool $sets whether the page title is set from the board name
	 */
	public function setSetsPageTitle($sets): void
	{
		$this->setViewState('SetsPageTitle', TPropertyValue::ensureBoolean($sets), true);
	}

	/**
	 * @return BEForumBoard the board
	 */
	public function getBoard(): BEForumBoard
	{
		if ($this->_board === null) {
			$this->_board = $this->getForum()->getBoards()->getViewableBoard($this->getBoardID());
		}
		return $this->_board;
	}

	/**
	 * Marks every thread of the board as read.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function markReadClicked($sender, $param): void
	{
		$this->attempt(function (): void {
			$this->getForum()->getReadTracker()->markBoardRead($this->getBoard());
		});
	}

	/**
	 * @return array<int, array{name: string, url: string}> the sub board rows
	 */
	public function getSubBoardRows(): array
	{
		$rows = [];
		foreach ($this->getForum()->getBoards()->getSubBoards($this->getBoard()) as $sub) {
			$rows[] = ['name' => $this->e((string) $sub->name), 'url' => $this->e($this->getUrls()->board($sub))];
		}
		return $rows;
	}

	/**
	 * @return string the moderators HTML, empty without moderators
	 */
	public function getModeratorsHtml(): string
	{
		$links = [];
		foreach ($this->getForum()->getMembers()->getBoardModerators((int) $this->getBoard()->getId()) as $moderator) {
			$links[] = $this->memberLink($moderator);
		}
		return $links ? $this->te('Moderators') . ': ' . implode(', ', $links) : '';
	}

	/**
	 * @return string the rendered description HTML
	 */
	public function getDescriptionHtml(): string
	{
		$board = $this->getBoard();
		return $board->description ? $this->getForum()->renderContent((string) $board->description) : '';
	}

	/**
	 * Loads the board and binds the header.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$board = $this->getBoard();
		if ($this->getSetsPageTitle()) {
			$this->setPageTitle((string) $board->name);
		}
		$this->Subscribe->setTargetType(BEForumSubscription::TYPE_BOARD);
		$this->Subscribe->setTargetID((int) $board->getId());
		$this->MarkRead->setVisible(!$this->getIsGuest());
		$this->bindRepeater('SubBoards', $this->getSubBoardRows());
		$this->SubBoards->setVisible(count($this->getSubBoardRows()) > 0);
	}
}
