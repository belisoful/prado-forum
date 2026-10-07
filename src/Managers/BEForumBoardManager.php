<?php

/**
 * BEForumBoardManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumBoardModerator;
use Belisoful\Forum\Data\BEForumCategory;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumSlug;

/**
 * BEForumBoardManager class.
 *
 * BEForumBoardManager manages the structure of the forum: the categories and
 * the (possibly nested) boards, their ordering, visibility and denormalised
 * counters, and answers which boards the current user may see.
 *
 * ```php
 * $category = $forum->getBoards()->createCategory('General');
 * $board = $forum->getBoards()->createBoard($category, 'Chat', 'Everything else');
 * foreach ($forum->getBoards()->getVisibleCategories() as $category) { ... }
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBoardManager extends BEForumManager
{
	// ------------------------------------------------------------------
	// Categories
	// ------------------------------------------------------------------

	/**
	 * @param bool $includeHidden whether to include hidden categories
	 * @return BEForumCategory[] the categories in display order
	 */
	public function getCategories(bool $includeHidden = false): array
	{
		$this->getDbConnection();
		$condition = $includeHidden ? null : 'is_hidden = ?';
		$params = $includeHidden ? [] : [false];
		return BEForumCategory::finder()->findAll(BEForumCategory::criteria($condition, $params, ['position' => 'asc', 'name' => 'asc']));
	}

	/**
	 * @return BEForumCategory[] the categories the current user may see (moderators see hidden ones)
	 */
	public function getVisibleCategories(): array
	{
		return $this->getCategories($this->isModerator());
	}

	/**
	 * @param int $id the category id
	 * @throws BEForumNotFoundException when the category does not exist
	 * @return BEForumCategory the category
	 */
	public function getCategory(int $id): BEForumCategory
	{
		$this->getDbConnection();
		$category = BEForumCategory::findOne($id);
		if ($category === null) {
			throw new BEForumNotFoundException('forum_category_not_found', $id);
		}
		return $category;
	}

	/**
	 * @param string $slug the category slug
	 * @return null|BEForumCategory the category
	 */
	public function findCategoryBySlug(string $slug): ?BEForumCategory
	{
		$this->getDbConnection();
		$category = BEForumCategory::finder()->find('slug = ?', [$slug]);
		return $category instanceof BEForumCategory ? $category : null;
	}

	/**
	 * Creates a category.
	 * @param string $name the name
	 * @param null|string $description the description
	 * @param null|int $position the position, appended when null
	 * @param bool $hidden whether the category is hidden
	 * @throws BEForumValidationException when the name is invalid
	 * @return BEForumCategory the category
	 */
	public function createCategory(string $name, ?string $description = null, ?int $position = null, bool $hidden = false): BEForumCategory
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$this->getDbConnection();
		$category = new BEForumCategory();
		$category->name = $this->validateText('name', $name, 1, 120, 'forum_name_required', 'forum_field_too_long');
		$category->slug = BEForumSlug::unique($category->name, fn (string $slug) => $this->findCategoryBySlug($slug) !== null, 100);
		$category->description = $description === null || trim($description) === '' ? null : trim($description);
		$category->position = $position ?? (BEForumCategory::countWhere() + 1);
		$category->is_hidden = $hidden;
		$category->save();
		$this->getModule()->getModeration()->log('create_category', 'category', $category->getId(), ['name' => $category->name]);
		$this->raise('onCategoryChanged', $category, ['action' => 'create']);
		return $category;
	}

	/**
	 * Updates a category.
	 * @param BEForumCategory $category the category
	 * @param array<string, mixed> $fields name, description, position, is_hidden, slug
	 * @throws BEForumValidationException when a value is invalid
	 * @return BEForumCategory the category
	 */
	public function updateCategory(BEForumCategory $category, array $fields): BEForumCategory
	{
		$category->refresh();
		$this->authorize(BEForumPermissions::ADMIN);
		if (array_key_exists('name', $fields)) {
			$category->name = $this->validateText('name', (string) $fields['name'], 1, 120, 'forum_name_required', 'forum_field_too_long');
		}
		if (array_key_exists('slug', $fields) && trim((string) $fields['slug']) !== '') {
			$slug = BEForumSlug::create((string) $fields['slug'], 100);
			$other = $this->findCategoryBySlug($slug);
			if ($other !== null && $other->getId() !== $category->getId()) {
				throw new BEForumValidationException('slug', 'forum_slug_taken', $slug);
			}
			$category->slug = $slug;
		}
		if (array_key_exists('description', $fields)) {
			$category->description = trim((string) $fields['description']) === '' ? null : trim((string) $fields['description']);
		}
		if (array_key_exists('position', $fields)) {
			$category->position = (int) $fields['position'];
		}
		if (array_key_exists('is_hidden', $fields)) {
			$category->is_hidden = (bool) $fields['is_hidden'];
		}
		$category->save();
		$this->getModule()->getModeration()->log('update_category', 'category', $category->getId(), ['fields' => array_keys($fields)]);
		$this->raise('onCategoryChanged', $category, ['action' => 'update']);
		return $category;
	}

	/**
	 * Deletes an empty category.
	 * @param BEForumCategory $category the category
	 * @throws BEForumValidationException when the category still has boards
	 */
	public function deleteCategory(BEForumCategory $category): void
	{
		$this->authorize(BEForumPermissions::ADMIN);
		if (BEForumBoard::countWhere('category_id = ?', [$category->getId()]) > 0) {
			throw new BEForumValidationException('category', 'forum_category_not_empty', $category->name);
		}
		$id = $category->getId();
		$name = $category->name;
		$category->delete();
		$this->getModule()->getModeration()->log('delete_category', 'category', $id, ['name' => $name]);
		$this->raise('onCategoryChanged', null, ['action' => 'delete', 'id' => $id, 'name' => $name]);
	}

	/**
	 * Reorders categories.
	 * @param int[] $orderedIds the category ids in the desired order
	 */
	public function reorderCategories(array $orderedIds): void
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$this->getDbConnection();
		$position = 1;
		foreach ($orderedIds as $id) {
			BEForumCategory::execute('UPDATE {table} SET position = :position WHERE id = :id', ['position' => $position++, 'id' => (int) $id]);
		}
	}

	// ------------------------------------------------------------------
	// Boards
	// ------------------------------------------------------------------

	/**
	 * @param int $id the board id
	 * @throws BEForumNotFoundException when the board does not exist
	 * @return BEForumBoard the board
	 */
	public function getBoard(int $id): BEForumBoard
	{
		$board = $this->findBoard($id);
		if ($board === null) {
			throw new BEForumNotFoundException('forum_board_not_found', $id);
		}
		return $board;
	}

	/**
	 * @param int $id the board id
	 * @return null|BEForumBoard the board
	 */
	public function findBoard(int $id): ?BEForumBoard
	{
		if ($id <= 0) {
			return null;
		}
		return $this->cached('board:' . $id, function () use ($id): ?BEForumBoard {
			$this->getDbConnection();
			return BEForumBoard::findOne($id);
		});
	}

	/**
	 * @param string $slug the board slug
	 * @return null|BEForumBoard the board
	 */
	public function findBoardBySlug(string $slug): ?BEForumBoard
	{
		$this->getDbConnection();
		$board = BEForumBoard::finder()->find('slug = ?', [$slug]);
		return $board instanceof BEForumBoard ? $board : null;
	}

	/**
	 * Returns a board the current user may view, or throws.
	 * @param int $id the board id
	 * @throws BEForumNotFoundException when the board does not exist
	 * @throws BEForumForbiddenException when the board may not be viewed
	 * @return BEForumBoard the board
	 */
	public function getViewableBoard(int $id): BEForumBoard
	{
		$board = $this->getBoard($id);
		$this->ensureViewable($board);
		return $board;
	}

	/**
	 * Throws unless the current user may view a board.
	 * @param BEForumBoard $board the board
	 * @throws BEForumForbiddenException when the board may not be viewed
	 */
	public function ensureViewable(BEForumBoard $board): void
	{
		if (!$this->canView($board)) {
			if ($this->getIsGuest() && $board->getIsPrivate()) {
				throw new BEForumForbiddenException(BEForumPermissions::VIEW, 'forum_login_required');
			}
			throw new BEForumForbiddenException(BEForumPermissions::VIEW, 'forum_board_not_viewable', $board->name);
		}
	}

	/**
	 * @param BEForumBoard $board the board
	 * @return bool whether the current user may view the board
	 */
	public function canView(BEForumBoard $board): bool
	{
		if (!$this->can(BEForumPermissions::VIEW, $this->extraFor($board))) {
			return false;
		}
		if ($board->getIsHidden() && !$this->isModerator($board)) {
			return false;
		}
		if ($board->getIsPrivate() && $this->getIsGuest()) {
			return false;
		}
		return true;
	}

	/**
	 * @param bool $includeHidden whether to include hidden boards
	 * @return BEForumBoard[] every board in category and position order
	 */
	public function getBoards(bool $includeHidden = false): array
	{
		$this->getDbConnection();
		$condition = $includeHidden ? null : 'is_hidden = ?';
		$params = $includeHidden ? [] : [false];
		return BEForumBoard::finder()->findAll(BEForumBoard::criteria($condition, $params, ['category_id' => 'asc', 'position' => 'asc', 'name' => 'asc']));
	}

	/**
	 * @return BEForumBoard[] the boards the current user may view
	 */
	public function getVisibleBoards(): array
	{
		return $this->cached('visible-boards', function (): array {
			$visible = [];
			foreach ($this->getBoards(true) as $board) {
				if ($this->canView($board)) {
					$visible[] = $board;
				}
			}
			return $visible;
		});
	}

	/**
	 * @return int[] the ids of the boards the current user may view
	 */
	public function getVisibleBoardIds(): array
	{
		return array_map(fn (BEForumBoard $board) => (int) $board->getId(), $this->getVisibleBoards());
	}

	/**
	 * Returns the visible top level boards of a category.
	 * @param BEForumCategory|int $category the category or its id
	 * @return BEForumBoard[] the boards
	 */
	public function getCategoryBoards($category): array
	{
		$categoryId = $category instanceof BEForumCategory ? $category->getId() : (int) $category;
		return array_values(array_filter($this->getVisibleBoards(), fn (BEForumBoard $board) => (int) $board->category_id === $categoryId && !$board->getIsSubBoard()));
	}

	/**
	 * Returns the visible sub boards of a board.
	 * @param BEForumBoard|int $board the board or its id
	 * @return BEForumBoard[] the sub boards
	 */
	public function getSubBoards($board): array
	{
		$boardId = $board instanceof BEForumBoard ? $board->getId() : (int) $board;
		return array_values(array_filter($this->getVisibleBoards(), fn (BEForumBoard $candidate) => (int) $candidate->parent_id === $boardId));
	}

	/**
	 * Returns the ancestors of a board from the top level down.
	 * @param BEForumBoard $board the board
	 * @return BEForumBoard[] the ancestors, nearest last
	 */
	public function getAncestors(BEForumBoard $board): array
	{
		$ancestors = [];
		$seen = [$board->getId() => true];
		$current = $board;
		while ($current->getIsSubBoard()) {
			$parent = $this->findBoard((int) $current->parent_id);
			if ($parent === null || isset($seen[$parent->getId()])) {
				break;
			}
			$seen[$parent->getId()] = true;
			array_unshift($ancestors, $parent);
			$current = $parent;
		}
		return $ancestors;
	}

	/**
	 * Returns a board and all its descendants.
	 * @param BEForumBoard $board the board
	 * @return int[] the board id followed by the descendant ids
	 */
	public function getDescendantIds(BEForumBoard $board): array
	{
		$byParent = [];
		foreach ($this->getBoards(true) as $candidate) {
			$byParent[(int) $candidate->parent_id][] = (int) $candidate->getId();
		}
		$ids = [(int) $board->getId()];
		$queue = [(int) $board->getId()];
		while ($queue) {
			$current = array_shift($queue);
			foreach ($byParent[$current] ?? [] as $child) {
				if (!in_array($child, $ids, true)) {
					$ids[] = $child;
					$queue[] = $child;
				}
			}
		}
		return $ids;
	}

	/**
	 * Creates a board.
	 * @param BEForumCategory|int $category the category or its id
	 * @param string $name the name
	 * @param null|string $description the description
	 * @param array<string, mixed> $options parent_id, position, is_locked, is_hidden, is_private, slug
	 * @throws BEForumValidationException when a value is invalid
	 * @return BEForumBoard the board
	 */
	public function createBoard($category, string $name, ?string $description = null, array $options = []): BEForumBoard
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$category = $category instanceof BEForumCategory ? $category : $this->getCategory((int) $category);
		$board = new BEForumBoard();
		$board->category_id = $category->getId();
		$board->name = $this->validateText('name', $name, 1, 120, 'forum_name_required', 'forum_field_too_long');
		$board->slug = BEForumSlug::unique($options['slug'] ?? $board->name, fn (string $slug) => $this->findBoardBySlug($slug) !== null, 100);
		$board->description = $description === null || trim($description) === '' ? null : trim($description);
		$board->parent_id = null;
		if (!empty($options['parent_id'])) {
			$parent = $this->getBoard((int) $options['parent_id']);
			if ((int) $parent->category_id !== $category->getId()) {
				throw new BEForumValidationException('parent_id', 'forum_board_parent_category');
			}
			$board->parent_id = $parent->getId();
		}
		$board->position = isset($options['position']) ? (int) $options['position'] : BEForumBoard::countWhere('category_id = ?', [$category->getId()]) + 1;
		$board->is_locked = !empty($options['is_locked']);
		$board->is_hidden = !empty($options['is_hidden']);
		$board->is_private = !empty($options['is_private']);
		$board->save();
		BEForumCategory::execute('UPDATE {table} SET board_count = board_count + 1 WHERE id = :id', ['id' => $category->getId()]);
		$this->flushRequestCache();
		$this->getModule()->getModeration()->log('create_board', BEForumNotification::TARGET_BOARD, $board->getId(), ['name' => $board->name]);
		$this->raise('onBoardChanged', $board, ['action' => 'create']);
		return $board;
	}

	/**
	 * Updates a board.
	 * @param BEForumBoard $board the board
	 * @param array<string, mixed> $fields name, slug, description, category_id, parent_id, position, is_locked, is_hidden, is_private
	 * @throws BEForumValidationException when a value is invalid
	 * @return BEForumBoard the board
	 */
	public function updateBoard(BEForumBoard $board, array $fields): BEForumBoard
	{
		$board->refresh();
		$this->authorize(BEForumPermissions::ADMIN);
		$previousCategory = (int) $board->category_id;
		if (array_key_exists('name', $fields)) {
			$board->name = $this->validateText('name', (string) $fields['name'], 1, 120, 'forum_name_required', 'forum_field_too_long');
		}
		if (array_key_exists('slug', $fields) && trim((string) $fields['slug']) !== '') {
			$slug = BEForumSlug::create((string) $fields['slug'], 100);
			$other = $this->findBoardBySlug($slug);
			if ($other !== null && $other->getId() !== $board->getId()) {
				throw new BEForumValidationException('slug', 'forum_slug_taken', $slug);
			}
			$board->slug = $slug;
		}
		if (array_key_exists('description', $fields)) {
			$board->description = trim((string) $fields['description']) === '' ? null : trim((string) $fields['description']);
		}
		if (array_key_exists('category_id', $fields)) {
			$board->category_id = $this->getCategory((int) $fields['category_id'])->getId();
			if ($board->parent_id && !array_key_exists('parent_id', $fields) && (int) $board->category_id !== $previousCategory) {
				throw new BEForumValidationException('category_id', 'forum_board_parent_category');
			}
		}
		if (array_key_exists('parent_id', $fields)) {
			$parentId = (int) $fields['parent_id'];
			if ($parentId > 0) {
				$parent = $this->getBoard($parentId);
				if ($parent->getId() === $board->getId() || in_array($parent->getId(), $this->getDescendantIds($board), true)) {
					throw new BEForumValidationException('parent_id', 'forum_board_parent_cycle');
				}
				if ((int) $parent->category_id !== (int) $board->category_id) {
					throw new BEForumValidationException('parent_id', 'forum_board_parent_category');
				}
				$board->parent_id = $parent->getId();
			} else {
				$board->parent_id = null;
			}
		}
		if (array_key_exists('position', $fields)) {
			$board->position = (int) $fields['position'];
		}
		foreach (['is_locked', 'is_hidden', 'is_private'] as $flag) {
			if (array_key_exists($flag, $fields)) {
				$board->$flag = (bool) $fields[$flag];
			}
		}
		$board->save();
		if ($previousCategory !== (int) $board->category_id) {
			BEForumCategory::execute('UPDATE {table} SET board_count = board_count - 1 WHERE id = :id AND board_count > 0', ['id' => $previousCategory]);
			BEForumCategory::execute('UPDATE {table} SET board_count = board_count + 1 WHERE id = :id', ['id' => (int) $board->category_id]);
			// sub boards follow their parent into the new category
			$descendants = $this->getDescendantIds($board);
			if ($descendants) {
				$moved = BEForumBoard::countWhere($this->inCondition('id', $descendants) . ' AND category_id = ?', [$previousCategory]);
				BEForumBoard::execute('UPDATE {table} SET category_id = :category WHERE ' . $this->inCondition('id', $descendants), ['category' => (int) $board->category_id]);
				BEForumCategory::execute('UPDATE {table} SET board_count = board_count - :moved WHERE id = :id AND board_count >= :moved', ['moved' => $moved, 'id' => $previousCategory]);
				BEForumCategory::execute('UPDATE {table} SET board_count = board_count + :moved WHERE id = :id', ['moved' => $moved, 'id' => (int) $board->category_id]);
			}
		}
		$this->flushRequestCache();
		$this->getModule()->getModeration()->log('update_board', BEForumNotification::TARGET_BOARD, $board->getId(), ['fields' => array_keys($fields)]);
		$this->raise('onBoardChanged', $board, ['action' => 'update']);
		return $board;
	}

	/**
	 * Deletes an empty board.
	 * @param BEForumBoard $board the board
	 * @throws BEForumValidationException when the board still has threads or sub boards
	 */
	public function deleteBoard(BEForumBoard $board): void
	{
		$this->authorize(BEForumPermissions::ADMIN);
		if (BEForumThread::countWhere('board_id = ?', [$board->getId()]) > 0) {
			throw new BEForumValidationException('board', 'forum_board_not_empty', $board->name);
		}
		if (BEForumBoard::countWhere('parent_id = ?', [$board->getId()]) > 0) {
			throw new BEForumValidationException('board', 'forum_board_has_children', $board->name);
		}
		$id = $board->getId();
		$name = $board->name;
		$categoryId = (int) $board->category_id;
		BEForumBoardModerator::finder()->deleteAll('board_id = ?', [$id]);
		$board->delete();
		BEForumCategory::execute('UPDATE {table} SET board_count = board_count - 1 WHERE id = :id AND board_count > 0', ['id' => $categoryId]);
		$this->flushRequestCache();
		$this->getModule()->getModeration()->log('delete_board', BEForumNotification::TARGET_BOARD, $id, ['name' => $name]);
		$this->raise('onBoardChanged', null, ['action' => 'delete', 'id' => $id, 'name' => $name]);
	}

	/**
	 * Reorders the boards of a category.
	 * @param int[] $orderedIds the board ids in the desired order
	 */
	public function reorderBoards(array $orderedIds): void
	{
		$this->authorize(BEForumPermissions::ADMIN);
		$this->getDbConnection();
		$position = 1;
		foreach ($orderedIds as $id) {
			BEForumBoard::execute('UPDATE {table} SET position = :position WHERE id = :id', ['position' => $position++, 'id' => (int) $id]);
		}
		$this->flushRequestCache();
	}

	/**
	 * Adjusts the counters of a board.
	 * @param int $boardId the board id
	 * @param int $threads the thread delta
	 * @param int $posts the post delta
	 */
	public function adjustCounters(int $boardId, int $threads, int $posts): void
	{
		if ($boardId <= 0 || ($threads === 0 && $posts === 0)) {
			return;
		}
		BEForumBoard::execute('UPDATE {table} SET thread_count = thread_count + :threads, post_count = post_count + :posts WHERE id = :id', ['threads' => $threads, 'posts' => $posts, 'id' => $boardId]);
		BEForumBoard::execute('UPDATE {table} SET thread_count = 0 WHERE id = :id AND thread_count < 0', ['id' => $boardId]);
		BEForumBoard::execute('UPDATE {table} SET post_count = 0 WHERE id = :id AND post_count < 0', ['id' => $boardId]);
		$this->flushRequestCache('board:' . $boardId);
	}

	/**
	 * Recomputes the last post information of a board from its visible threads.
	 * @param int $boardId the board id
	 */
	public function refreshLastPost(int $boardId): void
	{
		$this->getDbConnection();
		$thread = BEForumThread::finder()->find(BEForumThread::criteria('board_id = ? AND is_deleted = ? AND is_approved = ? AND last_post_id IS NOT NULL', [$boardId, false, true], ['last_post_at' => 'desc'], 1));
		if ($thread instanceof BEForumThread) {
			BEForumBoard::execute('UPDATE {table} SET last_thread_id = :thread, last_post_id = :post, last_post_at = :at, last_poster_member_id = :member WHERE id = :id', [
				'thread' => $thread->getId(),
				'post' => $thread->last_post_id,
				'at' => $thread->last_post_at,
				'member' => $thread->last_poster_member_id,
				'id' => $boardId,
			]);
		} else {
			BEForumBoard::execute('UPDATE {table} SET last_thread_id = NULL, last_post_id = NULL, last_post_at = NULL, last_poster_member_id = NULL WHERE id = :id', ['id' => $boardId]);
		}
		$this->flushRequestCache('board:' . $boardId);
	}

	/**
	 * Records a new post as the last post of a board.
	 * @param int $boardId the board id
	 * @param BEForumPost $post the post
	 */
	public function setLastPost(int $boardId, BEForumPost $post): void
	{
		BEForumBoard::execute('UPDATE {table} SET last_thread_id = :thread, last_post_id = :post, last_post_at = :at, last_poster_member_id = :member WHERE id = :id', [
			'thread' => (int) $post->thread_id,
			'post' => $post->getId(),
			'at' => $post->created_at,
			'member' => $post->member_id,
			'id' => $boardId,
		]);
		$this->flushRequestCache('board:' . $boardId);
	}

	/**
	 * Recomputes the counters and last post of every board and category.
	 * @return int the number of boards recounted
	 */
	public function recountAll(): int
	{
		$this->getDbConnection();
		$count = 0;
		foreach (BEForumBoard::finder()->findAll() as $board) {
			$threads = BEForumThread::countWhere('board_id = ? AND is_deleted = ? AND is_approved = ?', [$board->getId(), false, true]);
			$posts = BEForumPost::countWhere('board_id = :board AND is_deleted = :notdeleted AND is_approved = :approved AND ' . $this->getModule()->getPosts()->publicThreadCondition(), [':board' => $board->getId(), ':notdeleted' => false, ':approved' => true]);
			BEForumBoard::execute('UPDATE {table} SET thread_count = :threads, post_count = :posts WHERE id = :id', ['threads' => $threads, 'posts' => $posts, 'id' => $board->getId()]);
			$this->refreshLastPost($board->getId());
			$count++;
		}
		foreach (BEForumCategory::finder()->findAll() as $category) {
			BEForumCategory::execute('UPDATE {table} SET board_count = :boards WHERE id = :id', ['boards' => BEForumBoard::countWhere('category_id = ?', [$category->getId()]), 'id' => $category->getId()]);
		}
		$this->flushRequestCache();
		return $count;
	}
}
