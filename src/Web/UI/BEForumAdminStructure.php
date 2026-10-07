<?php

/**
 * BEForumAdminStructure class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumCategory;
use Belisoful\Forum\Security\BEForumPermissions;
use Prado\Web\UI\WebControls\TRepeater;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;
use Prado\Web\UI\WebControls\TRepeaterItem;
use Prado\Web\UI\WebControls\TTextBox;

/**
 * BEForumAdminStructure class.
 *
 * BEForumAdminStructure administers the categories and boards: creating,
 * editing, reordering and deleting them, and assigning board moderators.
 * It requires the `forum_admin` permission.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumAdminStructure />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TRepeater $Categories
 * @property \Prado\Web\UI\WebControls\TRepeater $Boards
 * @property \Prado\Web\UI\WebControls\TRepeater $Moderators
 * @property \Prado\Web\UI\WebControls\TTextBox $NewModerator
 * @property \Prado\Web\UI\WebControls\TLiteral $CategoryFormTitle
 * @property \Prado\Web\UI\WebControls\TTextBox $CategoryName
 * @property \Prado\Web\UI\WebControls\TTextBox $CategoryDescription
 * @property \Prado\Web\UI\WebControls\TCheckBox $CategoryHidden
 * @property \Prado\Web\UI\WebControls\TButton $SaveCategory
 * @property \Prado\Web\UI\WebControls\TLinkButton $CancelCategory
 * @property \Prado\Web\UI\WebControls\TLiteral $BoardFormTitle
 * @property \Prado\Web\UI\WebControls\TTextBox $BoardName
 * @property \Prado\Web\UI\WebControls\TTextBox $BoardDescription
 * @property \Prado\Web\UI\WebControls\TDropDownList $BoardCategory
 * @property \Prado\Web\UI\WebControls\TDropDownList $BoardParent
 * @property \Prado\Web\UI\WebControls\TCheckBox $BoardLocked
 * @property \Prado\Web\UI\WebControls\TCheckBox $BoardHidden
 * @property \Prado\Web\UI\WebControls\TCheckBox $BoardPrivate
 * @property \Prado\Web\UI\WebControls\TButton $SaveBoard
 * @property \Prado\Web\UI\WebControls\TLinkButton $CancelBoard
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumAdminStructure extends BEForumControl
{
	/**
	 * Requires the administration permission.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$this->requireLogin(BEForumPermissions::ADMIN);
		$this->getForum()->authorize(BEForumPermissions::ADMIN);
		$this->setPageTitle($this->t('Forum structure'));
	}

	/**
	 * @return int the category being edited, 0 for a new one
	 */
	public function getEditCategoryID(): int
	{
		return (int) $this->getViewState('EditCategoryID', 0);
	}

	/**
	 * @return int the board being edited, 0 for a new one
	 */
	public function getEditBoardID(): int
	{
		return (int) $this->getViewState('EditBoardID', 0);
	}

	/**
	 * Fills the board form selectors.
	 */
	protected function bindSelectors(): void
	{
		$boards = $this->getForum()->getBoards();
		$categories = [];
		foreach ($boards->getCategories(true) as $category) {
			$categories[(int) $category->getId()] = $this->e((string) $category->name);
		}
		$selectedCategory = (string) $this->BoardCategory->getSelectedValue();
		$this->BoardCategory->setDataSource($categories);
		$this->BoardCategory->dataBind();
		if ($selectedCategory !== '' && isset($categories[(int) $selectedCategory])) {
			$this->BoardCategory->setSelectedValue($selectedCategory);
		}
		$parents = [0 => $this->te('(top level)')];
		foreach ($boards->getBoards(true) as $board) {
			if ($board->getId() !== $this->getEditBoardID()) {
				$parents[(int) $board->getId()] = $this->e((string) $board->name);
			}
		}
		$selectedParent = (string) $this->BoardParent->getSelectedValue();
		$this->BoardParent->setDataSource($parents);
		$this->BoardParent->dataBind();
		if ($selectedParent !== '' && isset($parents[(int) $selectedParent])) {
			$this->BoardParent->setSelectedValue($selectedParent);
		}
	}

	/**
	 * Saves the category form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function saveCategoryClicked($sender, $param): void
	{
		$saved = $this->attempt(function (): void {
			$boards = $this->getForum()->getBoards();
			$fields = [
				'name' => (string) $this->CategoryName->getText(),
				'description' => (string) $this->CategoryDescription->getText(),
				'is_hidden' => $this->CategoryHidden->getChecked(),
			];
			if ($this->getEditCategoryID() > 0) {
				$boards->updateCategory($boards->getCategory($this->getEditCategoryID()), $fields);
			} else {
				$boards->createCategory($fields['name'], $fields['description'], null, $fields['is_hidden']);
			}
		});
		if ($saved) {
			$this->resetCategoryForm();
		}
	}

	/**
	 * Saves the board form.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function saveBoardClicked($sender, $param): void
	{
		$saved = $this->attempt(function (): void {
			$boards = $this->getForum()->getBoards();
			$fields = [
				'name' => (string) $this->BoardName->getText(),
				'description' => (string) $this->BoardDescription->getText(),
				'category_id' => (int) $this->BoardCategory->getSelectedValue(),
				'parent_id' => (int) $this->BoardParent->getSelectedValue(),
				'is_locked' => $this->BoardLocked->getChecked(),
				'is_hidden' => $this->BoardHidden->getChecked(),
				'is_private' => $this->BoardPrivate->getChecked(),
			];
			if ($this->getEditBoardID() > 0) {
				$boards->updateBoard($boards->getBoard($this->getEditBoardID()), $fields);
			} else {
				$boards->createBoard($fields['category_id'], $fields['name'], $fields['description'], $fields);
			}
		});
		if ($saved) {
			$this->resetBoardForm();
		}
	}

	/**
	 * Clears the category form.
	 */
	protected function resetCategoryForm(): void
	{
		$this->setViewState('EditCategoryID', 0, 0);
		$this->CategoryName->setText('');
		$this->CategoryDescription->setText('');
		$this->CategoryHidden->setChecked(false);
	}

	/**
	 * Clears the board form.
	 */
	protected function resetBoardForm(): void
	{
		$this->setViewState('EditBoardID', 0, 0);
		$this->BoardName->setText('');
		$this->BoardDescription->setText('');
		$this->BoardLocked->setChecked(false);
		$this->BoardHidden->setChecked(false);
		$this->BoardPrivate->setChecked(false);
	}

	/**
	 * Cancels the category edit.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function cancelCategoryClicked($sender, $param): void
	{
		$this->resetCategoryForm();
	}

	/**
	 * Cancels the board edit.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function cancelBoardClicked($sender, $param): void
	{
		$this->resetBoardForm();
	}

	/**
	 * Handles the category row commands.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function categoryCommand($sender, $param): void
	{
		$id = (int) $param->getCommandParameter();
		$boards = $this->getForum()->getBoards();
		$this->attempt(function () use ($param, $id, $boards): void {
			$category = $boards->getCategory($id);
			switch ($param->getCommandName()) {
				case 'edit':
					$this->setViewState('EditCategoryID', $id, 0);
					$this->CategoryName->setText((string) $category->name);
					$this->CategoryDescription->setText((string) $category->description);
					$this->CategoryHidden->setChecked($category->getIsHidden());
					break;
				case 'delete':
					$boards->deleteCategory($category);
					break;
				case 'up':
				case 'down':
					$this->move(array_map(fn (BEForumCategory $c) => (int) $c->getId(), $boards->getCategories(true)), $id, $param->getCommandName() === 'up', fn (array $ids) => $boards->reorderCategories($ids));
					break;
			}
		});
	}

	/**
	 * Handles the board row commands.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function boardCommand($sender, $param): void
	{
		$id = (int) $param->getCommandParameter();
		$boards = $this->getForum()->getBoards();
		$this->attempt(function () use ($param, $id, $boards): void {
			$board = $boards->getBoard($id);
			switch ($param->getCommandName()) {
				case 'edit':
					$this->setViewState('EditBoardID', $id, 0);
					$this->BoardName->setText((string) $board->name);
					$this->BoardDescription->setText((string) $board->description);
					$this->BoardLocked->setChecked($board->getIsLocked());
					$this->BoardHidden->setChecked($board->getIsHidden());
					$this->BoardPrivate->setChecked($board->getIsPrivate());
					$this->bindSelectors();
					$this->BoardCategory->setSelectedValue((string) (int) $board->category_id);
					$this->BoardParent->setSelectedValue((string) (int) $board->parent_id);
					break;
				case 'delete':
					$boards->deleteBoard($board);
					break;
				case 'up':
				case 'down':
					$siblings = array_values(array_filter($boards->getBoards(true), fn (BEForumBoard $b) => (int) $b->category_id === (int) $board->category_id));
					$this->move(array_map(fn (BEForumBoard $b) => (int) $b->getId(), $siblings), $id, $param->getCommandName() === 'up', fn (array $ids) => $boards->reorderBoards($ids));
					break;
			}
		});
	}

	/**
	 * Removes a moderator from a board.
	 * @param int $boardId the board id
	 * @param int $memberId the member id
	 */
	public function removeModerator(int $boardId, int $memberId): void
	{
		$this->attempt(function () use ($boardId, $memberId): void {
			$member = $this->getForum()->getMembers()->getMemberById($memberId);
			$this->getForum()->getMembers()->removeBoardModerator($boardId, $member);
		});
	}

	/**
	 * Adds a moderator to a board from the inline form.
	 * @param int $boardId the board id
	 * @param string $username the username to add
	 */
	public function addModerator(int $boardId, string $username): void
	{
		$this->attempt(function () use ($boardId, $username): void {
			$member = $this->getForum()->getMembers()->getMemberByUsername($username);
			$this->getForum()->getMembers()->addBoardModerator($boardId, $member);
		});
	}

	/**
	 * Moves an id one position in a list and persists the order.
	 * @param int[] $ids the ordered ids
	 * @param int $id the id to move
	 * @param bool $up whether to move up
	 * @param callable $persist `function(int[] $ids): void`
	 */
	protected function move(array $ids, int $id, bool $up, callable $persist): void
	{
		$index = array_search($id, $ids, true);
		if ($index === false) {
			return;
		}
		$target = $up ? $index - 1 : $index + 1;
		if ($target < 0 || $target >= count($ids)) {
			return;
		}
		[$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
		$persist($ids);
	}

	/**
	 * Handles the add-moderator command bubbling from a board row.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function moderatorCommand($sender, $param): void
	{
		if ($param->getCommandName() === 'addmod') {
			$box = $param->getItem()->findControl('NewModerator');
			$this->addModerator((int) $param->getCommandParameter(), $box instanceof TTextBox ? (string) $box->getText() : '');
		} elseif ($param->getCommandName() === 'removemod') {
			[$boardId, $memberId] = array_pad(explode(':', (string) $param->getCommandParameter(), 2), 2, 0);
			$this->removeModerator((int) $boardId, (int) $memberId);
		} else {
			$this->boardCommand($sender, $param);
		}
	}

	/**
	 * Builds the category rows with their boards (HTML escaped).
	 * @return array the rows
	 */
	public function getRows(): array
	{
		$forum = $this->getForum();
		$boards = $forum->getBoards();
		$all = $boards->getBoards(true);
		$rows = [];
		foreach ($boards->getCategories(true) as $category) {
			$boardRows = [];
			foreach ($all as $board) {
				if ((int) $board->category_id !== (int) $category->getId()) {
					continue;
				}
				$moderators = [];
				foreach ($forum->getMembers()->getBoardModerators((int) $board->getId()) as $moderator) {
					$moderators[] = ['id' => (int) $moderator->getId(), 'name' => $this->e($moderator->getDisplayName()), 'board' => (int) $board->getId()];
				}
				$boardRows[] = [
					'id' => (int) $board->getId(),
					'name' => $this->e((string) $board->name),
					'url' => $this->e($this->getUrls()->board($board)),
					'parent' => $board->getIsSubBoard(),
					'flags' => implode(' ', array_filter([$board->getIsLocked() ? $this->te('locked') : '', $board->getIsHidden() ? $this->te('hidden') : '', $board->getIsPrivate() ? $this->te('private') : ''])),
					'threads' => (int) $board->thread_count,
					'moderators' => $moderators,
				];
			}
			$rows[] = [
				'id' => (int) $category->getId(),
				'name' => $this->e((string) $category->name),
				'hidden' => $category->getIsHidden(),
				'boards' => $boardRows,
			];
		}
		return $rows;
	}

	/**
	 * Binds the boards of a category row.
	 * @param mixed $sender the repeater
	 * @param \Prado\Web\UI\WebControls\TRepeaterItemEventParameter $param the event parameter
	 */
	public function categoryDataBound($sender, $param): void
	{
		$item = $param->getItem();
		$boards = $item->findControl('Boards');
		$data = $item instanceof TRepeaterItem ? $item->getData() : null;
		if (is_array($data) && $boards instanceof TRepeater) {
			$boards->attachEventHandler('OnItemDataBound', [$this, 'boardDataBound']);
			$boards->attachEventHandler('OnItemCommand', [$this, 'moderatorCommand']);
			$boards->attachEventHandler('OnItemCreated', [$this, 'boardItemCreated']);
			$boards->setDataSource($data['boards']);
			$boards->dataBind();
		}
	}

	/**
	 * Re-attaches the nested repeater handlers after a postback restores the items.
	 * @param mixed $sender the repeater
	 * @param \Prado\Web\UI\WebControls\TRepeaterItemEventParameter $param the event parameter
	 */
	public function categoryItemCreated($sender, $param): void
	{
		$boards = $param->getItem()->findControl('Boards');
		if ($boards instanceof TRepeater) {
			$boards->attachEventHandler('OnItemCommand', [$this, 'moderatorCommand']);
			$boards->attachEventHandler('OnItemCreated', [$this, 'boardItemCreated']);
		}
	}

	/**
	 * Wires the nested moderator repeater of a board row (also on postback,
	 * when the rows are restored from view state).
	 * @param mixed $sender the boards repeater
	 * @param \Prado\Web\UI\WebControls\TRepeaterItemEventParameter $param the event parameter
	 */
	public function boardItemCreated($sender, $param): void
	{
		$moderators = $param->getItem()->findControl('Moderators');
		if ($moderators instanceof TRepeater) {
			$moderators->attachEventHandler('OnItemCommand', [$this, 'moderatorCommand']);
		}
	}

	/**
	 * Binds the moderators of a board row.
	 * @param mixed $sender the repeater
	 * @param \Prado\Web\UI\WebControls\TRepeaterItemEventParameter $param the event parameter
	 */
	public function boardDataBound($sender, $param): void
	{
		$item = $param->getItem();
		$moderators = $item->findControl('Moderators');
		$data = $item instanceof TRepeaterItem ? $item->getData() : null;
		if (is_array($data) && $moderators instanceof TRepeater) {
			$moderators->setDataSource($data['moderators']);
			$moderators->dataBind();
		}
	}

	/**
	 * Binds the lists and forms.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->bindSelectors();
		$this->bindRepeater('Categories', $this->getRows());
		$this->CategoryFormTitle->setText($this->te($this->getEditCategoryID() > 0 ? 'Edit category' : 'New category'));
		$this->BoardFormTitle->setText($this->te($this->getEditBoardID() > 0 ? 'Edit board' : 'New board'));
		$this->CancelCategory->setVisible($this->getEditCategoryID() > 0);
		$this->CancelBoard->setVisible($this->getEditBoardID() > 0);
	}
}
