<?php

/**
 * BEForumAdminMembers class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;
use Prado\Web\UI\WebControls\TTextBox;

/**
 * BEForumAdminMembers class.
 *
 * BEForumAdminMembers is the member administration: a searchable list with
 * ban/unban and warn actions, and (for administrators) the badge definitions.
 * It requires the `forum_moderate` permission.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumAdminMembers />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TTextBox $Query
 * @property \Prado\Web\UI\WebControls\TButton $Filter
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 * @property \Prado\Web\UI\WebControls\TTextBox $Reason
 * @property \Prado\Web\UI\WebControls\TTextBox $Until
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 * @property \Prado\Web\UI\WebControls\TPanel $BadgePanel
 * @property \Prado\Web\UI\WebControls\TRepeater $Badges
 * @property \Prado\Web\UI\WebControls\TTextBox $BadgeName
 * @property \Prado\Web\UI\WebControls\TTextBox $BadgeDescription
 * @property \Prado\Web\UI\WebControls\TTextBox $BadgeIcon
 * @property \Prado\Web\UI\WebControls\TButton $DefineBadge
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumAdminMembers extends BEForumControl
{
	/**
	 * Requires the moderation permission.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$this->requireLogin(BEForumPermissions::MODERATE);
		$this->getForum()->authorize(BEForumPermissions::MODERATE);
		$this->setPageTitle($this->t('Member administration'));
	}

	/**
	 * Fills the filter.
	 * @param mixed $param the event parameter
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		if (!$this->getPage()->getIsPostBack()) {
			$this->Query->setText($this->getRequestString(BEForumUrlBuilder::PARAM_QUERY));
		}
	}

	/**
	 * Redirects with the filter.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function filterClicked($sender, $param): void
	{
		$urls = $this->getUrls();
		$this->redirect($urls->build($urls->getPagePath('adminMembers'), [BEForumUrlBuilder::PARAM_QUERY => trim((string) $this->Query->getText())]));
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
				'id' => (int) $member->getId(),
				'name' => $this->memberLink($member),
				'username' => $this->e((string) $member->username),
				'joined' => $this->timeTag($member->joined_at),
				'posts' => (int) $member->post_count,
				'warnings' => (int) $member->warning_count,
				'banned' => $member->getIsBanned(),
				'status' => $member->getIsBanned() ? $this->te($member->banned_until ? 'banned until {0}' : 'banned', [$this->formatDate($member->banned_until)]) : '',
			];
		}
		return $rows;
	}

	/**
	 * Handles the member row commands.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function memberCommand($sender, $param): void
	{
		$id = (int) $param->getCommandParameter();
		$item = $param->getItem();
		$untilBox = $item->findControl('Until');
		$reasonBox = $item->findControl('Reason');
		$until = trim($untilBox instanceof TTextBox ? (string) $untilBox->getText() : '');
		$reason = $reasonBox instanceof TTextBox ? (string) $reasonBox->getText() : '';
		$this->attempt(function () use ($param, $id, $until, $reason): void {
			$members = $this->getForum()->getMembers();
			$member = $members->getMemberById($id);
			switch ($param->getCommandName()) {
				case 'ban':
					$members->ban($member, $until !== '' ? str_replace('T', ' ', $until) : null, $reason);
					break;
				case 'unban':
					$members->unban($member);
					break;
				case 'warn':
					$members->warn($member, $reason);
					break;
			}
		});
	}

	/**
	 * Defines a badge.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function defineBadgeClicked($sender, $param): void
	{
		$saved = $this->attempt(function (): void {
			$this->getForum()->getMembers()->defineBadge((string) $this->BadgeName->getText(), (string) $this->BadgeDescription->getText(), (string) $this->BadgeIcon->getText());
		});
		if ($saved) {
			$this->BadgeName->setText('');
			$this->BadgeDescription->setText('');
			$this->BadgeIcon->setText('');
		}
	}

	/**
	 * Binds the list and the badges.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$forum = $this->getForum();
		$query = $this->getRequestString(BEForumUrlBuilder::PARAM_QUERY);
		[$members, $pagination] = $forum->getMembers()->listMembers($this->getRequestedPage(), $query !== '' ? $query : null, 'newest');
		$this->bindRepeater('Rows', $this->buildRows($members));
		$this->Pager->setPagination($pagination);
		$urls = $this->getUrls();
		$this->Pager->setUrlCallback(fn (int $p) => $urls->build($urls->getPagePath('adminMembers'), [BEForumUrlBuilder::PARAM_QUERY => $query !== '' ? $query : null, BEForumUrlBuilder::PARAM_PAGE => $p > 1 ? $p : null]));
		$admin = $this->can(BEForumPermissions::ADMIN) && $forum->getEnableBadges();
		$this->BadgePanel->setVisible($admin);
		if ($admin) {
			$rows = [];
			foreach ($forum->getMembers()->getBadges() as $badge) {
				$rows[] = ['name' => $this->e((string) $badge->name), 'slug' => $this->e((string) $badge->slug), 'description' => $this->e((string) $badge->description)];
			}
			$this->bindRepeater('Badges', $rows);
		}
	}
}
