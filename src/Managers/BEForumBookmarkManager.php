<?php

/**
 * BEForumBookmarkManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumBookmark;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumPagination;

/**
 * BEForumBookmarkManager class.
 *
 * BEForumBookmarkManager keeps the personal list of saved posts of a member.
 *
 * ```php
 * $forum->getBookmarks()->toggle($post);
 * [$bookmarks, $pagination] = $forum->getBookmarks()->listBookmarks();
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBookmarkManager extends BEForumManager
{
	/**
	 * Bookmarks a post for the current member (idempotent).
	 * @param BEForumPost $post the post
	 * @throws BEForumValidationException when bookmarks are disabled
	 * @return BEForumBookmark the bookmark
	 */
	public function bookmark(BEForumPost $post): BEForumBookmark
	{
		if (!$this->getModule()->getEnableBookmarks()) {
			throw new BEForumValidationException('post', 'forum_bookmarks_disabled');
		}
		$this->authorize(BEForumPermissions::BOOKMARK, $this->extraFor((int) $post->board_id));
		$member = $this->requireMember(BEForumPermissions::BOOKMARK);
		$existing = $this->find($post, $member);
		if ($existing !== null) {
			return $existing;
		}
		$bookmark = new BEForumBookmark();
		$bookmark->member_id = $member->getId();
		$bookmark->post_id = $post->getId();
		$bookmark->save();
		$this->flushRequestCache();
		return $bookmark;
	}

	/**
	 * Removes the bookmark of the current member on a post.
	 * @param BEForumPost $post the post
	 * @return bool whether a bookmark was removed
	 */
	public function unbookmark(BEForumPost $post): bool
	{
		$member = $this->requireMember(BEForumPermissions::BOOKMARK);
		$existing = $this->find($post, $member);
		if ($existing === null) {
			return false;
		}
		$existing->delete();
		$this->flushRequestCache();
		return true;
	}

	/**
	 * Bookmarks or unbookmarks a post.
	 * @param BEForumPost $post the post
	 * @return bool whether the post is bookmarked after the call
	 */
	public function toggle(BEForumPost $post): bool
	{
		if ($this->isBookmarked($post)) {
			$this->unbookmark($post);
			return false;
		}
		$this->bookmark($post);
		return true;
	}

	/**
	 * @param BEForumPost $post the post
	 * @param BEForumMember $member the member
	 * @return null|BEForumBookmark the bookmark
	 */
	public function find(BEForumPost $post, BEForumMember $member): ?BEForumBookmark
	{
		$this->getDbConnection();
		$bookmark = BEForumBookmark::finder()->find('member_id = ? AND post_id = ?', [$member->getId(), $post->getId()]);
		return $bookmark instanceof BEForumBookmark ? $bookmark : null;
	}

	/**
	 * @param BEForumPost $post the post
	 * @return bool whether the current member bookmarked the post
	 */
	public function isBookmarked(BEForumPost $post): bool
	{
		return $this->getBookmarkedPostIds([(int) $post->getId()]) !== [];
	}

	/**
	 * @param int[] $postIds the post ids
	 * @return int[] the ids among them the current member bookmarked
	 */
	public function getBookmarkedPostIds(array $postIds): array
	{
		$member = $this->getMember();
		$postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
		if ($member === null || !$postIds || !$this->getModule()->getEnableBookmarks()) {
			return [];
		}
		$this->getDbConnection();
		return array_map(fn (BEForumBookmark $bookmark) => (int) $bookmark->post_id, BEForumBookmark::finder()->findAll($this->inCondition('post_id', $postIds) . ' AND member_id = ?', [$member->getId()]));
	}

	/**
	 * Lists the bookmarks of a member, newest first.
	 * @param null|BEForumMember $member the member, null for the current member
	 * @param int $page the 1-based page
	 * @param null|int $pageSize the page size
	 * @return array{0: BEForumBookmark[], 1: BEForumPagination} the bookmarks and the pagination
	 */
	public function listBookmarks(?BEForumMember $member = null, int $page = 1, ?int $pageSize = null): array
	{
		$member ??= $this->requireMember(BEForumPermissions::BOOKMARK);
		$this->getDbConnection();
		$pageSize ??= $this->getModule()->getItemsPerPage();
		$pagination = $this->paginate($page, $pageSize, BEForumBookmark::countWhere('member_id = ?', [$member->getId()]));
		return [BEForumBookmark::findAllPaged('member_id = ?', [$member->getId()], ['created_at' => 'desc', 'id' => 'desc'], $pageSize, $pagination->getPage()), $pagination];
	}
}
