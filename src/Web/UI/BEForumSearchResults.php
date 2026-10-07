<?php

/**
 * BEForumSearchResults class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Managers\BEForumSearchManager;
use Belisoful\Forum\Util\BEForumPagination;
use Belisoful\Forum\Web\BEForumUrlBuilder;

/**
 * BEForumSearchResults class.
 *
 * BEForumSearchResults is the search page: a form (query, board, posts or
 * threads) and the paged results rendered as posts ({@see BEForumPostView})
 * or thread rows ({@see BEForumThreadRow}).  The state comes from the request
 * parameters `q`, `board`, `type` and `page`.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumSearchResults />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TTextBox $Query
 * @property \Prado\Web\UI\WebControls\TDropDownList $Board
 * @property \Prado\Web\UI\WebControls\TRadioButtonList $Scope
 * @property \Prado\Web\UI\WebControls\TButton $Go
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TLabel $Summary
 * @property \Prado\Web\UI\WebControls\TPanel $PostResults
 * @property \Prado\Web\UI\WebControls\TRepeater $PostRows
 * @property \Prado\Web\UI\WebControls\TPanel $ThreadResults
 * @property \Prado\Web\UI\WebControls\TRepeater $ThreadRows
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSearchResults extends BEForumControl
{
	use BEForumPostRowsTrait;
	use BEForumThreadRowsTrait;

	/**
	 * @return string the query
	 */
	public function getQuery(): string
	{
		return $this->getRequestString(BEForumUrlBuilder::PARAM_QUERY);
	}

	/**
	 * @return string the scope: posts or threads
	 */
	public function getScope(): string
	{
		return $this->getRequestString(BEForumUrlBuilder::PARAM_TYPE) === BEForumSearchManager::SCOPE_THREADS ? BEForumSearchManager::SCOPE_THREADS : BEForumSearchManager::SCOPE_POSTS;
	}

	/**
	 * @return int the board filter, 0 for all
	 */
	public function getBoardID(): int
	{
		return $this->getRequestInt(BEForumUrlBuilder::PARAM_BOARD, 0);
	}

	/**
	 * Redirects to the search URL of the form values.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function searchClicked($sender, $param): void
	{
		$query = trim((string) $this->Query->getText());
		$board = (int) $this->Board->getSelectedValue();
		$this->redirect($this->getUrls()->search($query, $board > 0 ? $board : null, 1, (string) $this->Scope->getSelectedValue()));
	}

	/**
	 * Fills the form.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$this->setPageTitle($this->t('Search'));
		if (!$this->getPage()->getIsPostBack()) {
			$boards = [0 => $this->te('All boards')];
			foreach ($this->getForum()->getBoards()->getVisibleBoards() as $board) {
				$boards[(int) $board->getId()] = $this->e((string) $board->name);
			}
			$this->Board->setDataSource($boards);
			$this->Board->dataBind();
			$this->Board->setSelectedValue((string) $this->getBoardID());
			$this->Scope->setDataSource([BEForumSearchManager::SCOPE_POSTS => $this->te('Posts'), BEForumSearchManager::SCOPE_THREADS => $this->te('Threads')]);
			$this->Scope->dataBind();
			$this->Scope->setSelectedValue($this->getScope());
			$this->Query->setText($this->getQuery());
		}
	}

	/**
	 * Runs the search and binds the results.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		if ($this->getIsCallback()) {
			return;
		}
		$query = $this->getQuery();
		$this->PostResults->setVisible(false);
		$this->ThreadResults->setVisible(false);
		$this->Pager->setVisible(false);
		$this->Summary->setText('');
		if ($query === '') {
			return;
		}
		$forum = $this->getForum();
		$options = ['board' => $this->getBoardID() > 0 ? $this->getBoardID() : null, 'page' => $this->getRequestedPage()];
		$pagination = null;
		$this->attempt(function () use ($forum, $query, $options, &$pagination): void {
			if ($this->getScope() === BEForumSearchManager::SCOPE_THREADS) {
				[$threads, $pagination] = $forum->getSearch()->searchThreads($query, $options);
				$this->bindRepeater('ThreadRows', $this->buildThreadRows($threads, true));
				$this->ThreadResults->setVisible(true);
			} else {
				[$posts, $pagination] = $forum->getSearch()->searchPosts($query, $options);
				$this->bindRepeater('PostRows', $this->buildPostRows($posts, true, false));
				$this->PostResults->setVisible(true);
			}
		});
		if ($pagination instanceof BEForumPagination) {
			$this->Summary->setText($this->th('{0} results for "{1}"', [$pagination->getItemCount(), $this->e($query)]));
			$this->Pager->setVisible(true);
			$this->Pager->setPagination($pagination);
			$this->Pager->setUrlCallback(fn (int $p) => $this->getUrls()->search($query, $this->getBoardID() > 0 ? $this->getBoardID() : null, $p, $this->getScope()));
		}
	}
}
