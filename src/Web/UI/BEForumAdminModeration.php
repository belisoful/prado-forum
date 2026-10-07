<?php

/**
 * BEForumAdminModeration class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumModerationLog;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumReport;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Security\BEForumPermissions;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;
use Prado\Web\UI\WebControls\TTextBox;

/**
 * BEForumAdminModeration class.
 *
 * BEForumAdminModeration is the moderation dashboard: open reports (resolve
 * or dismiss with a note), the approval queue (approve or delete threads and
 * replies) and the moderation log.  It requires the `forum_moderate`
 * permission.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumAdminModeration />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 * @property \Prado\Web\UI\WebControls\TRepeater $Reports
 * @property \Prado\Web\UI\WebControls\TTextBox $Note
 * @property \Belisoful\Forum\Web\UI\BEForumPager $ReportPager
 * @property \Prado\Web\UI\WebControls\TRepeater $Pending
 * @property \Prado\Web\UI\WebControls\TRepeater $Log
 * @property \Belisoful\Forum\Web\UI\BEForumPager $LogPager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumAdminModeration extends BEForumControl
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
		$this->setPageTitle($this->t('Moderation'));
	}

	/**
	 * Builds the report rows (HTML escaped).
	 * @param BEForumReport[] $reports the reports
	 * @return array the rows
	 */
	public function buildReportRows(array $reports): array
	{
		$forum = $this->getForum();
		$posts = $forum->getPosts()->getPostsByIds(array_map(fn (BEForumReport $r) => (int) $r->post_id, $reports));
		$members = $forum->getMembers()->getMembersByIds(array_merge(
			array_map(fn (BEForumReport $r) => (int) $r->reporter_member_id, $reports),
			array_map(fn (BEForumPost $p) => (int) $p->member_id, $posts)
		));
		$rows = [];
		foreach ($reports as $report) {
			$post = $posts[(int) $report->post_id] ?? null;
			$rows[] = [
				'id' => (int) $report->getId(),
				'url' => $post ? $this->e($this->getUrls()->post($post)) : '',
				'excerpt' => $post ? $this->e($post->getExcerpt(160)) : $this->te('(post removed)'),
				'author' => $post ? $this->memberLink($post->member_id ? ($members[(int) $post->member_id] ?? null) : null, $post->guest_name) : '',
				'reporter' => $this->memberLink($report->reporter_member_id ? ($members[(int) $report->reporter_member_id] ?? null) : null),
				'reason' => $this->e((string) $report->reason),
				'time' => $this->timeTag($report->created_at),
			];
		}
		return $rows;
	}

	/**
	 * Builds the pending rows (HTML escaped).
	 * @param BEForumThread[] $threads the pending threads
	 * @param BEForumPost[] $posts the pending replies
	 * @return array the rows
	 */
	public function buildPendingRows(array $threads, array $posts): array
	{
		$forum = $this->getForum();
		$rows = [];
		foreach ($threads as $thread) {
			$rows[] = [
				'kind' => 'thread',
				'id' => (int) $thread->getId(),
				'label' => $this->te('Thread'),
				'title' => $this->e((string) $thread->title),
				'url' => $this->e($this->getUrls()->thread($thread)),
				'author' => $this->e($thread->getAuthorName()),
				'time' => $this->timeTag($thread->created_at),
			];
		}
		foreach ($posts as $post) {
			$thread = $forum->getThreads()->findThread((int) $post->thread_id);
			$rows[] = [
				'kind' => 'post',
				'id' => (int) $post->getId(),
				'label' => $this->te('Reply'),
				'title' => $this->e($thread ? (string) $thread->title : '') . ': ' . $this->e($post->getExcerpt(100)),
				'url' => $this->e($this->getUrls()->post($post)),
				'author' => $this->e($post->getAuthorName()),
				'time' => $this->timeTag($post->created_at),
			];
		}
		return $rows;
	}

	/**
	 * Builds the log rows (HTML escaped).
	 * @param BEForumModerationLog[] $entries the entries
	 * @return array the rows
	 */
	public function buildLogRows(array $entries): array
	{
		$members = $this->getForum()->getMembers()->getMembersByIds(array_map(fn (BEForumModerationLog $e) => (int) $e->member_id, $entries));
		$rows = [];
		foreach ($entries as $entry) {
			$details = $entry->getDetailsArray();
			$rows[] = [
				'who' => $this->memberLink($entry->member_id ? ($members[(int) $entry->member_id] ?? null) : null, $this->t('System')),
				'action' => $this->e(str_replace('_', ' ', (string) $entry->action)),
				'target' => $this->e((string) $entry->target_type . ' #' . (int) $entry->target_id),
				'details' => $this->e($details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''),
				'time' => $this->timeTag($entry->created_at),
			];
		}
		return $rows;
	}

	/**
	 * Handles the report commands.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function reportCommand($sender, $param): void
	{
		$id = (int) $param->getCommandParameter();
		$noteBox = $param->getItem()->findControl('Note');
		$note = $noteBox instanceof TTextBox ? (string) $noteBox->getText() : '';
		$this->attempt(function () use ($param, $id, $note): void {
			$moderation = $this->getForum()->getModeration();
			$report = $moderation->getReport($id);
			if ($param->getCommandName() === 'resolve') {
				$moderation->resolve($report, $note);
			} elseif ($param->getCommandName() === 'dismiss') {
				$moderation->dismiss($report, $note);
			}
		});
	}

	/**
	 * Handles the approval queue commands.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function pendingCommand($sender, $param): void
	{
		[$kind, $id] = array_pad(explode(':', (string) $param->getCommandParameter(), 2), 2, '');
		$this->attempt(function () use ($param, $kind, $id): void {
			$forum = $this->getForum();
			$approve = $param->getCommandName() === 'approve';
			if ($kind === 'thread') {
				$thread = $forum->getThreads()->findThread((int) $id);
				if ($thread !== null) {
					$approve ? $forum->getThreads()->approveThread($thread) : $forum->getThreads()->deleteThread($thread, 'rejected');
				}
			} else {
				$post = $forum->getPosts()->findPost((int) $id);
				if ($post !== null) {
					$approve ? $forum->getPosts()->approvePost($post) : $forum->getPosts()->deletePost($post, 'rejected');
				}
			}
		});
	}

	/**
	 * Binds the three sections.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$forum = $this->getForum();
		$moderation = $forum->getModeration();
		$urls = $this->getUrls();
		[$reports, $reportPages] = $moderation->listReports(BEForumReport::STATUS_OPEN, $this->getRequestInt('rpage', 1));
		$this->bindRepeater('Reports', $this->buildReportRows($reports));
		$this->ReportPager->setPagination($reportPages);
		$this->ReportPager->setUrlCallback(fn (int $p) => $urls->build($urls->getPagePath('adminModeration'), ['rpage' => $p > 1 ? $p : null]));
		[$threads] = $moderation->listPendingThreads(1, 50);
		[$posts] = $moderation->listPendingPosts(1, 50);
		$this->bindRepeater('Pending', $this->buildPendingRows($threads, $posts));
		[$log, $logPages] = $moderation->listLog($this->getRequestedPage());
		$this->bindRepeater('Log', $this->buildLogRows($log));
		$this->LogPager->setPagination($logPages);
		$this->LogPager->setUrlCallback(fn (int $p) => $urls->adminModeration($p));
	}
}
