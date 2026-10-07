<?php

/**
 * BEForumPager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumPager class.
 *
 * BEForumPager renders bookmarkable page links (`?page=N`) for a
 * {@see BEForumPagination}.  The owning control sets the pagination and a
 * URL callback before the pager renders:
 * ```php
 * $this->Pager->setPagination($pagination);
 * $this->Pager->setUrlCallback(fn (int $page) => $this->getUrls()->board($board, $page));
 * ```
 * The pager hides itself when there is a single page.
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Links
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumPager extends BEForumControl
{
	/** @var null|BEForumPagination the pagination */
	private ?BEForumPagination $_pagination = null;

	/** @var null|callable `function(int $page): string` */
	private $_urlCallback;

	/** @var int how many page numbers to show */
	private int $_window = 7;

	/**
	 * @return null|BEForumPagination the pagination
	 */
	public function getPagination(): ?BEForumPagination
	{
		return $this->_pagination;
	}

	/**
	 * @param null|BEForumPagination $pagination the pagination
	 */
	public function setPagination(?BEForumPagination $pagination): void
	{
		$this->_pagination = $pagination;
	}

	/**
	 * @return null|callable the URL callback
	 */
	public function getUrlCallback(): ?callable
	{
		return $this->_urlCallback;
	}

	/**
	 * @param null|callable $callback `function(int $page): string` building the URL of a page
	 */
	public function setUrlCallback(?callable $callback): void
	{
		$this->_urlCallback = $callback;
	}

	/**
	 * @return int how many page numbers to show
	 */
	public function getWindow(): int
	{
		return $this->_window;
	}

	/**
	 * @param int $window how many page numbers to show, at least 3
	 */
	public function setWindow($window): void
	{
		$this->_window = max(3, TPropertyValue::ensureInteger($window));
	}

	/**
	 * @param int $page a page number
	 * @return string the URL of the page
	 */
	public function getUrlFor(int $page): string
	{
		$callback = $this->_urlCallback;
		if ($callback === null) {
			// only the GET parameters are carried over, without the service parameter that constructUrl adds itself
			$params = array_filter($_GET, 'is_scalar');
			unset($params[$this->getRequest()->getServiceID()]);
			$params[BEForumUrlBuilder::PARAM_PAGE] = $page;
			return $this->getService()->constructUrl($this->getPage()->getPagePath(), $params, false);
		}
		return (string) $callback($page);
	}

	/**
	 * @return bool whether the pager has more than one page to show
	 */
	public function getHasPages(): bool
	{
		return $this->_pagination !== null && $this->_pagination->getPageCount() > 1;
	}

	/**
	 * Builds the link view model.
	 * @return array<int, array{label: string, url: string, current: bool, gap: bool, rel: string}> the items
	 */
	public function getItems(): array
	{
		if (!$this->getHasPages()) {
			return [];
		}
		$pagination = $this->_pagination;
		$items = [];
		if ($pagination->getHasPrevious()) {
			$items[] = ['label' => $this->e($this->t('Previous')), 'url' => $this->e($this->getUrlFor($pagination->getPage() - 1)), 'current' => false, 'gap' => false, 'rel' => 'prev'];
		}
		$previous = 0;
		foreach ($pagination->getPageNumbers($this->_window) as $number) {
			if ($previous > 0 && $number > $previous + 1) {
				$items[] = ['label' => '…', 'url' => '', 'current' => false, 'gap' => true, 'rel' => ''];
			}
			$items[] = ['label' => (string) $number, 'url' => $this->e($this->getUrlFor($number)), 'current' => $number === $pagination->getPage(), 'gap' => false, 'rel' => ''];
			$previous = $number;
		}
		if ($pagination->getHasNext()) {
			$items[] = ['label' => $this->e($this->t('Next')), 'url' => $this->e($this->getUrlFor($pagination->getPage() + 1)), 'current' => false, 'gap' => false, 'rel' => 'next'];
		}
		return $items;
	}

	/**
	 * @return string the summary text, e.g. "Page 2 of 5"
	 */
	public function getSummary(): string
	{
		if ($this->_pagination === null) {
			return '';
		}
		return $this->e($this->t('Page {0} of {1}', [$this->_pagination->getPage(), $this->_pagination->getPageCount()]));
	}

	/**
	 * Binds the links.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->setVisible($this->getHasPages());
		$this->bindRepeater('Links', $this->getItems());
	}
}
