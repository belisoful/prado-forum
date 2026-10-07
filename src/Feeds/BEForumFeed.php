<?php

/**
 * BEForumFeed class file.
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
use Belisoful\Forum\Util\BEForumTime;
use Prado\Prado;
use Prado\TApplicationComponent;
use Prado\TPropertyValue;
use Prado\Web\Services\IFeedContentProvider;

/**
 * BEForumFeed class.
 *
 * BEForumFeed provides RSS 2.0 or Atom feeds of the forum through PRADO's
 * {@see \Prado\Web\Services\TFeedService}:
 * ```xml
 * <service id="feed" class="Prado\Web\Services\TFeedService">
 *   <feed id="forum" class="Belisoful\Forum\Feeds\BEForumFeed" />
 *   <feed id="forum-atom" class="Belisoful\Forum\Feeds\BEForumFeed" Format="atom" Scope="posts" />
 * </service>
 * ```
 * `?feed=forum` lists the latest threads, `?feed=forum&board=3` the threads of
 * a board and `?feed=forum&thread=7` the posts of a thread.  {@see setScope}
 * chooses between `threads` and `posts`; the request parameters `board` and
 * `thread` narrow the feed.  Only content visible to the current user is
 * included.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumFeed extends TApplicationComponent implements IFeedContentProvider
{
	public const FORMAT_RSS = 'rss';
	public const FORMAT_ATOM = 'atom';
	public const SCOPE_THREADS = 'threads';
	public const SCOPE_POSTS = 'posts';

	/** @var string the feed format */
	private string $_format = self::FORMAT_RSS;
	/** @var string the feed scope */
	private string $_scope = self::SCOPE_THREADS;
	/** @var int the maximum number of items */
	private int $_limit = 20;
	/** @var null|string the module id */
	private ?string $_moduleId = null;
	/** @var null|BEForumModule the module */
	private ?BEForumModule $_module = null;

	/**
	 * @param mixed $config the feed configuration
	 */
	public function init($config)
	{
	}

	/**
	 * @return string rss or atom
	 */
	public function getFormat(): string
	{
		return $this->_format;
	}

	/**
	 * @param string $format rss or atom
	 * @throws BEForumConfigurationException when the format is unknown
	 */
	public function setFormat($format): void
	{
		$format = strtolower(TPropertyValue::ensureString($format));
		if (!in_array($format, [self::FORMAT_RSS, self::FORMAT_ATOM], true)) {
			throw new BEForumConfigurationException('forum_feed_format_invalid', $format);
		}
		$this->_format = $format;
	}

	/**
	 * @return string threads or posts
	 */
	public function getScope(): string
	{
		return $this->_scope;
	}

	/**
	 * @param string $scope threads or posts
	 * @throws BEForumConfigurationException when the scope is unknown
	 */
	public function setScope($scope): void
	{
		$scope = strtolower(TPropertyValue::ensureString($scope));
		if (!in_array($scope, [self::SCOPE_THREADS, self::SCOPE_POSTS], true)) {
			throw new BEForumConfigurationException('forum_feed_scope_invalid', $scope);
		}
		$this->_scope = $scope;
	}

	/**
	 * @return int the maximum number of items
	 */
	public function getLimit(): int
	{
		return $this->_limit;
	}

	/**
	 * @param int $limit the maximum number of items, 1 to 100
	 */
	public function setLimit($limit): void
	{
		$this->_limit = max(1, min(100, TPropertyValue::ensureInteger($limit)));
	}

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
	 * @return string the content type of the feed
	 */
	public function getContentType()
	{
		return $this->_format === self::FORMAT_ATOM ? 'application/atom+xml' : 'application/rss+xml';
	}

	/**
	 * Collects the feed items for the current request.
	 * @return array<int, array{title: string, link: string, description: string, author: string, date: int, id: string}> the items
	 */
	public function getItems(): array
	{
		$module = $this->getForumModule();
		$request = $this->getRequest();
		$boardId = (int) ($request ? $request->itemAt('board') : 0);
		$threadId = (int) ($request ? $request->itemAt('thread') : 0);
		$urls = $module->getUrls();
		$items = [];
		if ($threadId > 0 || $this->_scope === self::SCOPE_POSTS) {
			if ($threadId > 0) {
				$thread = $module->getThreads()->getThread($threadId);
				[$posts, $pagination] = $module->getPosts()->listPosts($thread, 1, $this->_limit);
				if ($pagination->getPageCount() > 1) {
					// the newest posts are on the last page
					[$posts] = $module->getPosts()->listPosts($thread, $pagination->getPageCount(), $this->_limit);
				}
				$posts = array_reverse($posts);
			} else {
				$posts = $module->getPosts()->getRecentPosts($this->_limit, $boardId > 0 ? $boardId : null);
			}
			$threads = $module->getThreads()->getThreadsByIds(array_map(fn (BEForumPost $post) => (int) $post->thread_id, $posts));
			foreach ($posts as $post) {
				$thread = $threads[(int) $post->thread_id] ?? null;
				$items[] = [
					'title' => ($thread ? $thread->title : '') . ($post->getIsFirstPost() ? '' : ' (' . Prado::localize('reply') . ')'),
					'link' => $urls->absolute($urls->post($post)),
					'description' => (string) $post->content_html,
					'author' => $post->getAuthorName(),
					'date' => BEForumTime::parse($post->created_at) ?? BEForumTime::timestamp(),
					'id' => 'post-' . $post->getId(),
				];
			}
		} else {
			$threads = $module->getThreads()->getRecentThreads($this->_limit, $boardId > 0 ? $boardId : null);
			$firstPosts = $module->getPosts()->getPostsByIds(array_map(fn (BEForumThread $thread) => (int) $thread->first_post_id, $threads));
			foreach ($threads as $thread) {
				$first = $firstPosts[(int) $thread->first_post_id] ?? null;
				$items[] = [
					'title' => (string) $thread->title,
					'link' => $urls->absolute($urls->thread($thread)),
					'description' => $first ? (string) $first->content_html : '',
					'author' => $thread->getAuthorName(),
					'date' => BEForumTime::parse($thread->created_at) ?? BEForumTime::timestamp(),
					'id' => 'thread-' . $thread->getId(),
				];
			}
		}
		return $items;
	}

	/**
	 * @return string the feed XML
	 */
	public function getFeedContent()
	{
		$module = $this->getForumModule();
		$urls = $module->getUrls();
		$items = $this->getItems();
		$title = $module->getTitle();
		$link = $urls->absolute($urls->index());
		return $this->_format === self::FORMAT_ATOM ? $this->renderAtom($title, $link, $items) : $this->renderRss($title, $link, $items);
	}

	/**
	 * @param string $text a text
	 * @return string the XML escaped text
	 */
	protected function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Renders an RSS 2.0 document.
	 * @param string $title the channel title
	 * @param string $link the channel link
	 * @param array $items the items
	 * @return string the XML
	 */
	protected function renderRss(string $title, string $link, array $items): string
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
		$xml .= '<title>' . $this->escape($title) . '</title>';
		$xml .= '<link>' . $this->escape($link) . '</link>';
		$xml .= '<description>' . $this->escape($title) . '</description>';
		$xml .= '<lastBuildDate>' . gmdate(DATE_RSS, $items ? max(array_column($items, 'date')) : BEForumTime::timestamp()) . '</lastBuildDate>';
		foreach ($items as $item) {
			$xml .= '<item>';
			$xml .= '<title>' . $this->escape($item['title']) . '</title>';
			$xml .= '<link>' . $this->escape($item['link']) . '</link>';
			$xml .= '<guid isPermaLink="false">' . $this->escape($item['id']) . '</guid>';
			$xml .= '<pubDate>' . gmdate(DATE_RSS, $item['date']) . '</pubDate>';
			$xml .= '<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">' . $this->escape($item['author']) . '</dc:creator>';
			$xml .= '<description>' . $this->escape($item['description']) . '</description>';
			$xml .= '</item>';
		}
		$xml .= '</channel></rss>';
		return $xml;
	}

	/**
	 * Renders an Atom 1.0 document.
	 * @param string $title the feed title
	 * @param string $link the feed link
	 * @param array $items the items
	 * @return string the XML
	 */
	protected function renderAtom(string $title, string $link, array $items): string
	{
		$updated = $items ? max(array_column($items, 'date')) : BEForumTime::timestamp();
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<feed xmlns="http://www.w3.org/2005/Atom">';
		$xml .= '<title>' . $this->escape($title) . '</title>';
		$xml .= '<link href="' . $this->escape($link) . '"/>';
		$xml .= '<id>' . $this->escape($link) . '</id>';
		$xml .= '<updated>' . gmdate(DATE_ATOM, $updated) . '</updated>';
		foreach ($items as $item) {
			$xml .= '<entry>';
			$xml .= '<title>' . $this->escape($item['title']) . '</title>';
			$xml .= '<link href="' . $this->escape($item['link']) . '"/>';
			$xml .= '<id>' . $this->escape($item['link'] . '#' . $item['id']) . '</id>';
			$xml .= '<updated>' . gmdate(DATE_ATOM, $item['date']) . '</updated>';
			$xml .= '<author><name>' . $this->escape($item['author']) . '</name></author>';
			$xml .= '<content type="html">' . $this->escape($item['description']) . '</content>';
			$xml .= '</entry>';
		}
		$xml .= '</feed>';
		return $xml;
	}
}
