<?php

/**
 * BEForumPagination class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Util;

use Prado\TComponent;

/**
 * BEForumPagination class.
 *
 * BEForumPagination is a value object describing one page of a list: the
 * 1-based {@see getPage current page}, the {@see getPageSize page size}, the
 * total {@see getItemCount item count} and everything derived from them
 * ({@see getPageCount}, {@see getOffset}, {@see getHasNext}, ...).  Managers
 * return it next to the records of the page and the pager control renders it.
 *
 * ```php
 * $pagination = new BEForumPagination(2, 20, 95);
 * $pagination->getOffset();     // 20
 * $pagination->getPageCount();  // 5
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPagination extends TComponent
{
	/** @var int the 1-based current page */
	private int $_page;
	/** @var int items per page */
	private int $_pageSize;
	/** @var int total number of items */
	private int $_itemCount;

	/**
	 * @param int $page the requested 1-based page, clamped into the valid range
	 * @param int $pageSize items per page, at least 1
	 * @param int $itemCount total number of items, at least 0
	 */
	public function __construct(int $page = 1, int $pageSize = 20, int $itemCount = 0)
	{
		$this->_pageSize = max(1, $pageSize);
		$this->_itemCount = max(0, $itemCount);
		$this->_page = min(max(1, $page), $this->getPageCount());
		parent::__construct();
	}

	/**
	 * @return int the 1-based current page
	 */
	public function getPage(): int
	{
		return $this->_page;
	}

	/**
	 * @return int items per page
	 */
	public function getPageSize(): int
	{
		return $this->_pageSize;
	}

	/**
	 * @return int total number of items
	 */
	public function getItemCount(): int
	{
		return $this->_itemCount;
	}

	/**
	 * @return int number of pages, at least 1
	 */
	public function getPageCount(): int
	{
		return max(1, (int) ceil($this->_itemCount / $this->_pageSize));
	}

	/**
	 * @return int the SQL offset of the first item of the page
	 */
	public function getOffset(): int
	{
		return ($this->_page - 1) * $this->_pageSize;
	}

	/**
	 * @return bool whether a page follows the current one
	 */
	public function getHasNext(): bool
	{
		return $this->_page < $this->getPageCount();
	}

	/**
	 * @return bool whether a page precedes the current one
	 */
	public function getHasPrevious(): bool
	{
		return $this->_page > 1;
	}

	/**
	 * @return int the 1-based index of the first item on the page, 0 when empty
	 */
	public function getFirstItem(): int
	{
		return $this->_itemCount === 0 ? 0 : $this->getOffset() + 1;
	}

	/**
	 * @return int the 1-based index of the last item on the page, 0 when empty
	 */
	public function getLastItem(): int
	{
		return min($this->_itemCount, $this->getOffset() + $this->_pageSize);
	}

	/**
	 * Computes the page numbers to render around the current page.
	 * @param int $window how many page numbers to show at most
	 * @return int[] the page numbers, always containing the first and last page
	 */
	public function getPageNumbers(int $window = 7): array
	{
		$count = $this->getPageCount();
		$window = max(3, $window);
		if ($count <= $window) {
			return range(1, $count);
		}
		$half = (int) floor(($window - 2) / 2);
		$start = max(2, $this->_page - $half);
		$end = min($count - 1, $start + ($window - 3));
		$start = max(2, $end - ($window - 3));
		$pages = [1];
		for ($i = $start; $i <= $end; $i++) {
			$pages[] = $i;
		}
		$pages[] = $count;
		return $pages;
	}

	/**
	 * @param int $itemIndex a 0-based item index
	 * @return int the 1-based page containing the item
	 */
	public function getPageOfItem(int $itemIndex): int
	{
		return (int) floor(max(0, $itemIndex) / $this->_pageSize) + 1;
	}
}
