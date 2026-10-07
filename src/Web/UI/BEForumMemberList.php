<?php

/**
 * BEForumMemberList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\TPropertyValue;

/**
 * BEForumMemberList class.
 *
 * BEForumMemberList is the paged member directory with a name filter and a
 * sort order (name, reputation, posts, newest).  The filter and sort come
 * from the `q` and `sort` request parameters.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumMemberList />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TTextBox $Query
 * @property \Prado\Web\UI\WebControls\TDropDownList $SortList
 * @property \Prado\Web\UI\WebControls\TButton $Filter
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumMemberList extends BEForumControl
{
	/**
	 * @return int the page size, 0 for the module default
	 */
	public function getPageSize(): int
	{
		return (int) $this->getViewState('PageSize', 0);
	}

	/**
	 * @param int $size the page size, 0 for the module default
	 */
	public function setPageSize($size): void
	{
		$this->setViewState('PageSize', max(0, TPropertyValue::ensureInteger($size)), 0);
	}

	/**
	 * @return string the sort order
	 */
	public function getSort(): string
	{
		$sort = $this->getRequestString('sort', 'username');
		return in_array($sort, ['username', 'reputation', 'posts', 'newest'], true) ? $sort : 'username';
	}

	/**
	 * Redirects with the filter values.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function filterClicked($sender, $param): void
	{
		$urls = $this->getUrls();
		$this->redirect($urls->build($urls->getPagePath('members'), [BEForumUrlBuilder::PARAM_QUERY => trim((string) $this->Query->getText()), 'sort' => (string) $this->SortList->getSelectedValue()]));
	}

	/**
	 * Fills the filter form.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$this->setPageTitle($this->t('Members'));
		if (!$this->getPage()->getIsPostBack()) {
			$this->Query->setText($this->getRequestString(BEForumUrlBuilder::PARAM_QUERY));
			$this->SortList->setDataSource(['username' => $this->te('Name'), 'reputation' => $this->te('Reputation'), 'posts' => $this->te('Posts'), 'newest' => $this->te('Newest')]);
			$this->SortList->dataBind();
			$this->SortList->setSelectedValue($this->getSort());
		}
	}

	/**
	 * Builds the rows (HTML escaped).
	 * @param BEForumMember[] $members the members
	 * @return array the rows
	 */
	public function buildRows(array $members): array
	{
		$rows = [];
		foreach ($members as $member) {
			$rows[] = [
				'avatar' => $this->e($this->avatarUrl($member, 32)),
				'name' => $this->memberLink($member),
				'username' => $this->e((string) $member->username),
				'joined' => $this->timeTag($member->joined_at),
				'posts' => (int) $member->post_count,
				'reputation' => (int) $member->reputation,
				'banned' => $member->getIsBanned(),
			];
		}
		return $rows;
	}

	/**
	 * Loads and binds the members.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$query = $this->getRequestString(BEForumUrlBuilder::PARAM_QUERY);
		$sort = $this->getSort();
		[$members, $pagination] = $this->getForum()->getMembers()->listMembers($this->getRequestedPage(), $query !== '' ? $query : null, $sort, $this->getPageSize() > 0 ? $this->getPageSize() : null);
		$this->bindRepeater('Rows', $this->buildRows($members));
		$this->Pager->setPagination($pagination);
		$urls = $this->getUrls();
		$this->Pager->setUrlCallback(fn (int $p) => $urls->build($urls->getPagePath('members'), [BEForumUrlBuilder::PARAM_QUERY => $query !== '' ? $query : null, 'sort' => $sort, BEForumUrlBuilder::PARAM_PAGE => $p > 1 ? $p : null]));
	}
}
