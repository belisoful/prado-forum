<?php

/**
 * BEForumPostRowsTrait trait file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Managers\BEForumAttachmentManager;

/**
 * BEForumPostRowsTrait trait.
 *
 * BEForumPostRowsTrait builds the view model rows consumed by
 * {@see BEForumPostView} from a list of posts, loading authors, reply targets,
 * reactions, attachments and bookmarks in batches.  It is shared by the post
 * list, the search results, the bookmark list and the member profile.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
trait BEForumPostRowsTrait
{
	/**
	 * Builds the post rows for the post view renderer.
	 * @param BEForumPost[] $posts the posts
	 * @param bool $withContext whether to include the thread title/link (lists spanning threads)
	 * @param bool $showQuote whether the quote action is offered
	 * @return array the rows
	 */
	/** @var int the post whose report form is open, 0 for none */
	private int $_reportPostId = 0;

	/** @var array<int, string> error messages (HTML escaped) keyed by post id */
	private array $_postErrors = [];

	/**
	 * Remembers which post shows its report form; the rows are rebuilt at
	 * pre-render, so the renderer state cannot be kept in the items.
	 * @param int $postId the post id, 0 closes the form
	 */
	public function openReportFor(int $postId): void
	{
		$this->_reportPostId = $postId;
	}

	/**
	 * Remembers an error to display inside a post row.
	 * @param int $postId the post id
	 * @param string $message the message (plain text)
	 */
	public function setPostError(int $postId, string $message): void
	{
		$this->_postErrors[$postId] = $this->e($message);
	}

	public function buildPostRows(array $posts, bool $withContext = false, bool $showQuote = true): array
	{
		$forum = $this->getForum();
		$urls = $this->getUrls();
		$postIds = array_map(fn (BEForumPost $post) => (int) $post->getId(), $posts);
		$memberIds = [];
		$replyIds = [];
		$threadIds = [];
		foreach ($posts as $post) {
			$memberIds[] = (int) $post->member_id;
			$memberIds[] = (int) $post->edited_by_member_id;
			$replyIds[] = (int) $post->reply_to_post_id;
			$threadIds[] = (int) $post->thread_id;
		}
		$replies = $forum->getPosts()->getPostsByIds($replyIds);
		foreach ($replies as $reply) {
			$memberIds[] = (int) $reply->member_id;
		}
		$members = $forum->getMembers()->getMembersByIds($memberIds);
		$threads = $forum->getThreads()->getThreadsByIds($threadIds);
		$reactions = $forum->getEnableReactions() ? $forum->getReactions()->getSummaries($postIds) : [];
		$memberReactions = $forum->getEnableReactions() ? $forum->getReactions()->getMemberReactions($postIds) : [];
		$attachments = $forum->getAttachments()->getAttachmentsForPosts($postIds);
		$bookmarked = $forum->getBookmarks()->getBookmarkedPostIds($postIds);
		$rows = [];
		foreach ($posts as $post) {
			$thread = $threads[(int) $post->thread_id] ?? null;
			$author = $post->member_id ? ($members[(int) $post->member_id] ?? null) : null;
			$editor = $post->edited_by_member_id ? ($members[(int) $post->edited_by_member_id] ?? null) : null;
			$replyTo = $post->reply_to_post_id ? ($replies[(int) $post->reply_to_post_id] ?? null) : null;
			$files = [];
			foreach ($attachments[(int) $post->getId()] ?? [] as $attachment) {
				$files[] = [
					'name' => $this->e((string) $attachment->file_name),
					'url' => $this->e($urls->attachment($attachment)),
					'size' => $this->e(BEForumAttachmentManager::formatSize((int) $attachment->size)),
					'image' => $attachment->getIsInlineImage(),
				];
			}
			$edited = '';
			if ($post->getIsEdited()) {
				$edited = $this->th('Edited {0}', [$this->timeTag($post->edited_at)]);
				if ($editor !== null && (int) $post->edited_by_member_id !== (int) $post->member_id) {
					$edited .= ' ' . $this->te('by') . ' ' . $this->memberLink($editor);
				}
			}
			$rows[] = [
				'post' => $post,
				'author' => $author,
				'id' => (int) $post->getId(),
				'position' => (int) $post->position,
				'url' => $this->e($urls->post($post)),
				'created' => $this->timeTag($post->created_at),
				'html' => (string) $post->content_html,
				'signature' => $forum->getEnableSignatures() && $author !== null && $author->signature ? $forum->renderContent((string) $author->signature) : '',
				'edited' => $edited,
				'deleted' => $post->getIsDeleted(),
				'pending' => !$post->getIsApproved(),
				'accepted' => $thread !== null && (int) $thread->accepted_post_id === (int) $post->getId(),
				'first' => $post->getIsFirstPost(),
				'reportOpen' => $this->_reportPostId === (int) $post->getId(),
				'error' => $this->_postErrors[(int) $post->getId()] ?? '',
				'replyToUrl' => $replyTo ? $this->e($urls->post($replyTo)) : '',
				'replyToAuthor' => $replyTo ? $this->e($replyTo->getAuthorName()) : '',
				'threadTitle' => $withContext && $thread ? $this->e((string) $thread->title) : '',
				'threadUrl' => $withContext && $thread ? $this->e($urls->thread($thread)) : '',
				'attachments' => $files,
				'reactions' => $reactions[(int) $post->getId()] ?? [],
				'memberReaction' => $memberReactions[(int) $post->getId()] ?? '',
				'bookmarked' => in_array((int) $post->getId(), $bookmarked, true),
				'showQuote' => $showQuote,
			];
		}
		return $rows;
	}
}
