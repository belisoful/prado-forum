<?php

/**
 * BEForumRecentPosts class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumPost;
use Prado\TPropertyValue;

/**
 * BEForumRecentPosts class.
 *
 * BEForumRecentPosts lists the newest visible posts of the forum or of a
 * board with an excerpt, useful as a sidebar panel.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumRecentPosts Limit="5" />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumRecentPosts extends BEForumControl
{
	/**
	 * @return int the maximum number of posts
	 */
	public function getLimit(): int
	{
		return (int) $this->getViewState('Limit', 10);
	}

	/**
	 * @param int $limit the maximum number of posts
	 */
	public function setLimit($limit): void
	{
		$this->setViewState('Limit', max(1, TPropertyValue::ensureInteger($limit)), 10);
	}

	/**
	 * @return int a board to restrict to, 0 for the whole forum
	 */
	public function getBoardID(): int
	{
		return (int) $this->getViewState('BoardID', 0);
	}

	/**
	 * @param int $id a board to restrict to, 0 for the whole forum
	 */
	public function setBoardID($id): void
	{
		$this->setViewState('BoardID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return int the excerpt length in characters
	 */
	public function getExcerptLength(): int
	{
		return (int) $this->getViewState('ExcerptLength', 120);
	}

	/**
	 * @param int $length the excerpt length in characters
	 */
	public function setExcerptLength($length): void
	{
		$this->setViewState('ExcerptLength', max(20, TPropertyValue::ensureInteger($length)), 120);
	}

	/**
	 * Builds the rows (HTML escaped).
	 * @return array<int, array{url: string, title: string, author: string, time: string, excerpt: string}> the rows
	 */
	public function getRows(): array
	{
		$forum = $this->getForum();
		$posts = $forum->getPosts()->getRecentPosts($this->getLimit(), $this->getBoardID() > 0 ? $this->getBoardID() : null);
		$threads = $forum->getThreads()->getThreadsByIds(array_map(fn (BEForumPost $post) => (int) $post->thread_id, $posts));
		$members = $forum->getMembers()->getMembersByIds(array_map(fn (BEForumPost $post) => (int) $post->member_id, $posts));
		$rows = [];
		foreach ($posts as $post) {
			$thread = $threads[(int) $post->thread_id] ?? null;
			$rows[] = [
				'url' => $this->e($this->getUrls()->post($post)),
				'title' => $this->e($thread ? (string) $thread->title : ''),
				'author' => $this->memberLink($post->member_id ? ($members[(int) $post->member_id] ?? null) : null, $post->guest_name),
				'time' => $this->timeTag($post->created_at),
				'excerpt' => $this->e($post->getExcerpt($this->getExcerptLength())),
			];
		}
		return $rows;
	}

	/**
	 * Binds the rows.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->bindRepeater('Rows', $this->getRows());
	}
}
