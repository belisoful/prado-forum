<?php

/**
 * BEForumModerationManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumModerationLog;
use Belisoful\Forum\Data\BEForumNotification;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumReport;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;

/**
 * BEForumModerationManager class.
 *
 * BEForumModerationManager handles reports (flags) on posts, the approval
 * queue and the audit log of every moderation and administration action.
 *
 * ```php
 * $forum->getModeration()->report($post, 'Spam link');
 * [$reports, $pagination] = $forum->getModeration()->listReports(BEForumReport::STATUS_OPEN);
 * $forum->getModeration()->resolve($report, 'Post removed');
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumModerationManager extends BEForumManager
{
	/**
	 * Writes an entry to the moderation log.
	 * @param string $action the action name
	 * @param string $targetType the target type
	 * @param int $targetId the target id
	 * @param array $details extra details
	 * @return BEForumModerationLog the entry
	 */
	public function log(string $action, string $targetType, int $targetId, array $details = []): BEForumModerationLog
	{
		$this->getDbConnection();
		$entry = new BEForumModerationLog();
		$entry->member_id = $this->getMember()?->getId();
		$entry->action = mb_substr($action, 0, 64);
		$entry->target_type = mb_substr($targetType, 0, 16);
		$entry->target_id = $targetId;
		$entry->setJsonColumn('details', $details ?: null);
		$entry->save();
		$this->raise('onModerationAction', $entry, ['action' => $action]);
		return $entry;
	}

	/**
	 * Lists the moderation log, newest first.
	 * @param int $page the 1-based page
	 * @param array<string, mixed> $filters action, target_type, target_id, member_id
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumModerationLog[], 1: BEForumPagination} the entries and the pagination
	 */
	public function listLog(int $page = 1, array $filters = [], ?int $pageSize = null): array
	{
		$this->authorize(BEForumPermissions::MODERATE);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$conditions = ['1=1'];
		$params = [];
		foreach (['action', 'target_type'] as $field) {
			if (!empty($filters[$field])) {
				$conditions[] = $field . ' = :' . $field;
				$params[':' . $field] = (string) $filters[$field];
			}
		}
		foreach (['target_id', 'member_id'] as $field) {
			if (!empty($filters[$field])) {
				$conditions[] = $field . ' = :' . $field;
				$params[':' . $field] = (int) $filters[$field];
			}
		}
		$condition = implode(' AND ', $conditions);
		$pagination = $this->paginate($page, $pageSize, BEForumModerationLog::countWhere($condition, $params));
		return [BEForumModerationLog::findAllPaged($condition, $params, ['created_at' => 'desc', 'id' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * Reports a post to the moderators.
	 * @param BEForumPost $post the post
	 * @param string $reason the reason
	 * @throws BEForumValidationException when the reason is empty
	 * @return BEForumReport the report (an existing open report of the same member is reused)
	 */
	public function report(BEForumPost $post, string $reason): BEForumReport
	{
		$this->authorize(BEForumPermissions::REPORT, $this->extraFor((int) $post->board_id));
		$member = $this->requireMember(BEForumPermissions::REPORT);
		$reason = $this->validateText('reason', $reason, 1, 2000, 'forum_reason_required', 'forum_field_too_long');
		$this->getDbConnection();
		$existing = BEForumReport::finder()->find('post_id = ? AND reporter_member_id = ? AND status = ?', [$post->getId(), $member->getId(), BEForumReport::STATUS_OPEN]);
		if ($existing instanceof BEForumReport) {
			$existing->reason = $reason;
			$existing->save();
			$this->flushRequestCache('open-reports');
			return $existing;
		}
		$report = new BEForumReport();
		$report->post_id = $post->getId();
		$report->reporter_member_id = $member->getId();
		$report->reason = $reason;
		$report->status = BEForumReport::STATUS_OPEN;
		$report->save();
		$this->flushRequestCache('open-reports');
		$thread = $this->getModule()->getThreads()->findThread((int) $post->thread_id);
		$moderators = $this->getModule()->getMembers()->getBoardModerators((int) $post->board_id);
		$this->getModule()->getNotifications()->notifyMany($moderators, BEForumNotification::TYPE_REPORT, $member, BEForumNotification::TARGET_POST, $post->getId(), ['thread_id' => (int) $post->thread_id, 'title' => $thread?->title, 'reason' => $reason]);
		$this->raise('onReportCreated', $report, ['post' => $post]);
		return $report;
	}

	/**
	 * @param int $id the report id
	 * @throws BEForumNotFoundException when the report does not exist
	 * @return BEForumReport the report
	 */
	public function getReport(int $id): BEForumReport
	{
		$this->authorize(BEForumPermissions::MODERATE);
		$this->getDbConnection();
		$report = BEForumReport::findOne($id);
		if ($report === null) {
			throw new BEForumNotFoundException('forum_report_not_found', $id);
		}
		return $report;
	}

	/**
	 * Lists reports.
	 * @param null|string $status open, resolved, dismissed or null for all
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumReport[], 1: BEForumPagination} the reports and the pagination
	 */
	public function listReports(?string $status = BEForumReport::STATUS_OPEN, int $page = 1, ?int $pageSize = null): array
	{
		$this->authorize(BEForumPermissions::MODERATE);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = '1=1';
		$params = [];
		if ($status !== null) {
			if (!in_array($status, BEForumReport::getStatuses(), true)) {
				throw new BEForumValidationException('status', 'forum_report_status_invalid', $status);
			}
			$condition = 'status = :status';
			$params[':status'] = $status;
		}
		$pagination = $this->paginate($page, $pageSize, BEForumReport::countWhere($condition, $params));
		return [BEForumReport::findAllPaged($condition, $params, ['created_at' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * @return int the number of open reports
	 */
	public function countOpenReports(): int
	{
		return $this->cached('open-reports', function (): int {
			$this->getDbConnection();
			return BEForumReport::countWhere('status = ?', [BEForumReport::STATUS_OPEN]);
		});
	}

	/**
	 * Closes a report.
	 * @param BEForumReport $report the report
	 * @param string $status resolved or dismissed
	 * @param null|string $resolution the moderator note
	 * @throws BEForumValidationException when the status is not a closing status
	 * @return BEForumReport the report
	 */
	public function handleReport(BEForumReport $report, string $status, ?string $resolution = null): BEForumReport
	{
		$post = $this->getModule()->getPosts()->findPost((int) $report->post_id);
		$this->authorize(BEForumPermissions::MODERATE, $this->extraFor($post ? (int) $post->board_id : null));
		if (!in_array($status, [BEForumReport::STATUS_RESOLVED, BEForumReport::STATUS_DISMISSED], true)) {
			throw new BEForumValidationException('status', 'forum_report_status_invalid', $status);
		}
		$report->status = $status;
		$report->handled_by_member_id = $this->getMember()?->getId();
		$report->handled_at = $this->now();
		$report->resolution = $resolution === null || trim($resolution) === '' ? null : trim($resolution);
		$report->save();
		$this->flushRequestCache('open-reports');
		$this->log('handle_report', 'report', $report->getId(), ['status' => $status, 'resolution' => $report->resolution, 'post_id' => (int) $report->post_id]);
		$this->raise('onReportHandled', $report, ['status' => $status]);
		return $report;
	}

	/**
	 * Marks a report resolved.
	 * @param BEForumReport $report the report
	 * @param null|string $resolution the moderator note
	 * @return BEForumReport the report
	 */
	public function resolve(BEForumReport $report, ?string $resolution = null): BEForumReport
	{
		return $this->handleReport($report, BEForumReport::STATUS_RESOLVED, $resolution);
	}

	/**
	 * Marks a report dismissed.
	 * @param BEForumReport $report the report
	 * @param null|string $resolution the moderator note
	 * @return BEForumReport the report
	 */
	public function dismiss(BEForumReport $report, ?string $resolution = null): BEForumReport
	{
		return $this->handleReport($report, BEForumReport::STATUS_DISMISSED, $resolution);
	}

	/**
	 * Lists the threads awaiting approval.
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumThread[], 1: BEForumPagination} the threads and the pagination
	 */
	public function listPendingThreads(int $page = 1, ?int $pageSize = null): array
	{
		$this->authorize(BEForumPermissions::MODERATE);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = 'is_approved = ? AND is_deleted = ?';
		$params = [false, false];
		$pagination = $this->paginate($page, $pageSize, BEForumThread::countWhere($condition, $params));
		return [BEForumThread::findAllPaged($condition, $params, ['created_at' => 'asc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * Lists the replies awaiting approval (opening posts are listed with their threads).
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumPost[], 1: BEForumPagination} the posts and the pagination
	 */
	public function listPendingPosts(int $page = 1, ?int $pageSize = null): array
	{
		$this->authorize(BEForumPermissions::MODERATE);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$condition = 'is_approved = ? AND is_deleted = ? AND position > 1';
		$params = [false, false];
		$pagination = $this->paginate($page, $pageSize, BEForumPost::countWhere($condition, $params));
		return [BEForumPost::findAllPaged($condition, $params, ['created_at' => 'asc'], $pageSize, $pagination->getPage()), $pagination];
	}

	/**
	 * @return int the number of threads and replies awaiting approval
	 */
	public function countPending(): int
	{
		return $this->cached('pending', function (): int {
			$this->getDbConnection();
			return BEForumThread::countWhere('is_approved = ? AND is_deleted = ?', [false, false]) + BEForumPost::countWhere('is_approved = ? AND is_deleted = ? AND position > 1', [false, false]);
		});
	}
}
