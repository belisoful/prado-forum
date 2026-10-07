<?php

/**
 * BEForumSubscriptionList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Security\BEForumPermissions;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;

/**
 * BEForumSubscriptionList class.
 *
 * BEForumSubscriptionList shows the boards and threads the current member
 * follows and lets the member unsubscribe.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumSubscriptionList />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSubscriptionList extends BEForumControl
{
	/**
	 * Requires a logged in member.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$this->requireLogin(BEForumPermissions::SUBSCRIBE);
		$this->setPageTitle($this->t('Subscriptions'));
	}

	/**
	 * Builds the rows (HTML escaped).
	 * @param BEForumSubscription[] $subscriptions the subscriptions
	 * @return array the rows
	 */
	public function buildRows(array $subscriptions): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$threadIds = [];
		foreach ($subscriptions as $subscription) {
			if ($subscription->target_type === BEForumSubscription::TYPE_THREAD) {
				$threadIds[] = (int) $subscription->target_id;
			}
		}
		$threads = $forum->getThreads()->getThreadsByIds($threadIds);
		$rows = [];
		foreach ($subscriptions as $subscription) {
			$title = '';
			$url = '';
			if ($subscription->target_type === BEForumSubscription::TYPE_THREAD) {
				$thread = $threads[(int) $subscription->target_id] ?? null;
				$title = $thread ? (string) $thread->title : $this->t('(removed thread)');
				$url = $thread ? $urls->thread($thread) : '';
			} else {
				$board = $forum->getBoards()->findBoard((int) $subscription->target_id);
				$title = $board ? (string) $board->name : $this->t('(removed board)');
				$url = $board ? $urls->board($board) : '';
			}
			$rows[] = [
				'type' => $subscription->target_type,
				'typeLabel' => $this->te($subscription->target_type === BEForumSubscription::TYPE_THREAD ? 'Thread' : 'Board'),
				'target' => (int) $subscription->target_id,
				'title' => $this->e($title),
				'url' => $this->e($url),
				'since' => $this->timeTag($subscription->created_at),
			];
		}
		return $rows;
	}

	/**
	 * Unsubscribes.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function itemCommand($sender, $param): void
	{
		[$type, $id] = array_pad(explode(':', (string) $param->getCommandParameter(), 2), 2, '');
		$this->attempt(fn () => $this->getForum()->getSubscriptions()->unsubscribe($type, (int) $id));
	}

	/**
	 * Loads and binds the subscriptions.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		[$subscriptions, $pagination] = $this->getForum()->getSubscriptions()->listSubscriptions(null, $this->getRequestedPage());
		$this->bindRepeater('Rows', $this->buildRows($subscriptions));
		$this->Pager->setPagination($pagination);
		$this->Pager->setUrlCallback(fn (int $p) => $this->getUrls()->subscriptions($p));
	}
}
