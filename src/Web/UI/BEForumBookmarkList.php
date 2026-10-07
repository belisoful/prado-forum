<?php

/**
 * BEForumBookmarkList class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumBookmark;
use Belisoful\Forum\Security\BEForumPermissions;

/**
 * BEForumBookmarkList class.
 *
 * BEForumBookmarkList shows the posts the current member bookmarked, newest
 * first, rendered with {@see BEForumPostView} (whose bookmark action removes
 * them again).
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumBookmarkList />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 * @property \Belisoful\Forum\Web\UI\BEForumPager $Pager
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBookmarkList extends BEForumControl
{
	use BEForumPostRowsTrait;

	/**
	 * Requires a logged in member.
	 * @param mixed $param the event parameter
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$this->requireLogin(BEForumPermissions::BOOKMARK);
		$this->setPageTitle($this->t('Bookmarks'));
	}

	/**
	 * Loads and binds the bookmarked posts.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$forum = $this->getForum();
		if ($this->getIsCallback()) {
			return;
		}
		[$bookmarks, $pagination] = $forum->getBookmarks()->listBookmarks(null, $this->getRequestedPage());
		$posts = $forum->getPosts()->getPostsByIds(array_map(fn (BEForumBookmark $bookmark) => (int) $bookmark->post_id, $bookmarks));
		$ordered = [];
		foreach ($bookmarks as $bookmark) {
			$post = $posts[(int) $bookmark->post_id] ?? null;
			if ($post !== null && $forum->getPosts()->isVisible($post)) {
				$ordered[] = $post;
			}
		}
		$this->bindRepeater('Rows', $this->buildPostRows($ordered, true, false));
		$this->Pager->setPagination($pagination);
		$this->Pager->setUrlCallback(fn (int $p) => $this->getUrls()->bookmarks($p));
	}
}
