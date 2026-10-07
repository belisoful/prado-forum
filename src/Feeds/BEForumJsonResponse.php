<?php

/**
 * BEForumJsonResponse class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Feeds;

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Prado\TPropertyValue;
use Prado\Web\Services\TJsonResponse;

/**
 * BEForumJsonResponse class.
 *
 * BEForumJsonResponse exposes a small read-only JSON API through PRADO's
 * {@see \Prado\Web\Services\TJsonService}:
 * ```xml
 * <service id="json" class="Prado\Web\Services\TJsonService">
 *   <json id="forum" class="Belisoful\Forum\Feeds\BEForumJsonResponse" />
 * </service>
 * ```
 * Requests select the data with the `action` parameter:
 *  - `?json=forum&action=stats` the statistics summary;
 *  - `?json=forum&action=threads[&board=3][&page=2]` recent or board threads;
 *  - `?json=forum&action=posts&thread=7[&page=2]` the posts of a thread;
 *  - `?json=forum&action=search&q=prado[&board=3][&page=2]` post search;
 *  - `?json=forum&action=tags` the popular tags.
 * Only content visible to the current user is returned.  Errors are reported
 * as `{"error": "..."}`.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumJsonResponse extends TJsonResponse
{
	/** @var null|string the module id */
	private ?string $_moduleId = null;
	/** @var null|BEForumModule the module */
	private ?BEForumModule $_module = null;

	/**
	 * @return null|string the forum module id, null for the first forum module
	 */
	public function getModuleID(): ?string
	{
		return $this->_moduleId;
	}

	/**
	 * @param string $id the forum module id
	 */
	public function setModuleID($id): void
	{
		$this->_moduleId = TPropertyValue::ensureString($id) ?: null;
	}

	/**
	 * @throws BEForumConfigurationException when no forum module is found
	 * @return BEForumModule the forum module
	 */
	public function getForumModule(): BEForumModule
	{
		if ($this->_module === null) {
			$app = $this->getApplication();
			$module = $this->_moduleId !== null ? $app->getModule($this->_moduleId) : null;
			if ($module === null) {
				foreach ($app->getModulesByType(BEForumModule::class) as $candidate) {
					if ($candidate instanceof BEForumModule) {
						$module = $candidate;
						break;
					}
				}
			}
			if (!($module instanceof BEForumModule)) {
				throw new BEForumConfigurationException('forum_module_not_found', (string) $this->_moduleId);
			}
			$this->_module = $module;
		}
		return $this->_module;
	}

	/**
	 * @param BEForumModule $module the forum module
	 */
	public function setForumModule(BEForumModule $module): void
	{
		$this->_module = $module;
	}

	/**
	 * @param string $name a request parameter
	 * @param mixed $default the default value
	 * @return mixed the parameter value
	 */
	protected function param(string $name, $default = null)
	{
		$request = $this->getRequest();
		$value = $request ? $request->itemAt($name) : null;
		return $value === null ? $default : $value;
	}

	/**
	 * @return array the JSON content for the requested action
	 */
	public function getJsonContent()
	{
		try {
			return $this->buildContent((string) $this->param('action', 'stats'));
		} catch (BEForumValidationException $e) {
			return ['error' => $e->getMessage()];
		} catch (\Prado\Exceptions\THttpException $e) {
			return ['error' => $e->getMessage(), 'status' => $e->getStatusCode()];
		}
	}

	/**
	 * @param string $action the action
	 * @return array the content
	 */
	protected function buildContent(string $action): array
	{
		$module = $this->getForumModule();
		$page = max(1, (int) $this->param('page', 1));
		switch ($action) {
			case 'threads':
				$boardId = (int) $this->param('board', 0);
				if ($boardId > 0) {
					$board = $module->getBoards()->getViewableBoard($boardId);
					[$threads, $pagination] = $module->getThreads()->listThreads($board, $page);
					return ['board' => ['id' => $board->getId(), 'name' => $board->name], 'threads' => array_map([$this, 'threadToArray'], $threads), 'page' => $pagination->getPage(), 'pages' => $pagination->getPageCount(), 'total' => $pagination->getItemCount()];
				}
				return ['threads' => array_map([$this, 'threadToArray'], $module->getThreads()->getRecentThreads($module->getThreadsPerPage()))];
			case 'posts':
				$thread = $module->getThreads()->getThread((int) $this->param('thread', 0));
				[$posts, $pagination] = $module->getPosts()->listPosts($thread, $page);
				return ['thread' => $this->threadToArray($thread), 'posts' => array_map([$this, 'postToArray'], $posts), 'page' => $pagination->getPage(), 'pages' => $pagination->getPageCount(), 'total' => $pagination->getItemCount()];
			case 'search':
				$boardId = (int) $this->param('board', 0);
				[$posts, $pagination] = $module->getSearch()->searchPosts((string) $this->param('q', ''), ['board' => $boardId > 0 ? $boardId : null, 'page' => $page]);
				return ['posts' => array_map([$this, 'postToArray'], $posts), 'page' => $pagination->getPage(), 'pages' => $pagination->getPageCount(), 'total' => $pagination->getItemCount()];
			case 'tags':
				return ['tags' => array_map(fn ($tag) => ['slug' => $tag->slug, 'name' => $tag->name, 'threads' => (int) $tag->thread_count], $module->getTags()->getPopularTags(50))];
			case 'stats':
			default:
				return ['stats' => $module->getStatistics()->getSummary()];
		}
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return array the thread as an array
	 */
	protected function threadToArray(BEForumThread $thread): array
	{
		$urls = $this->getForumModule()->getUrls();
		return [
			'id' => $thread->getId(),
			'board_id' => (int) $thread->board_id,
			'title' => $thread->title,
			'slug' => $thread->slug,
			'type' => $thread->type,
			'author' => $thread->getAuthorName(),
			'pinned' => $thread->getIsPinned(),
			'locked' => $thread->getIsLocked(),
			'solved' => $thread->getIsSolved(),
			'replies' => (int) $thread->reply_count,
			'views' => (int) $thread->view_count,
			'created_at' => $thread->created_at,
			'last_post_at' => $thread->last_post_at,
			'url' => $urls->absolute($urls->thread($thread)),
		];
	}

	/**
	 * @param BEForumPost $post the post
	 * @return array the post as an array
	 */
	protected function postToArray(BEForumPost $post): array
	{
		$urls = $this->getForumModule()->getUrls();
		return [
			'id' => $post->getId(),
			'thread_id' => (int) $post->thread_id,
			'position' => (int) $post->position,
			'author' => $post->getAuthorName(),
			'html' => (string) $post->content_html,
			'reactions' => (int) $post->reaction_count,
			'edited' => $post->getIsEdited(),
			'created_at' => $post->created_at,
			'url' => $urls->absolute($urls->post($post)),
		];
	}
}
