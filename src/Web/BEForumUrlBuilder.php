<?php

/**
 * BEForumUrlBuilder class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web;

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumBoard;
use Belisoful\Forum\Data\BEForumMember;
use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumTag;
use Belisoful\Forum\Data\BEForumThread;
use Prado\Prado;
use Prado\TComponent;
use Prado\TPropertyValue;

/**
 * BEForumUrlBuilder class.
 *
 * BEForumUrlBuilder constructs every URL of the forum so page paths and GET
 * parameter names live in exactly one place.  Pages are addressed as
 * `<PagePathPrefix>.<Name>` (`Forum.Thread` by default) and hosts may rewrite
 * any URL through the `dyBuildUrl` dynamic event or remap page names through
 * the `*Page` properties.  With {@see \Prado\Web\TUrlMapping} configured in the
 * host application the generated URLs become pretty automatically.
 *
 * GET parameters: `board`, `thread`, `post`, `page`, `member`, `tag`, `q`, `type`.
 *
 * @method string dyBuildUrl(string $url, string $pagePath, array $params, null|string $anchor)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumUrlBuilder extends TComponent
{
	public const PARAM_BOARD = 'board';
	public const PARAM_THREAD = 'thread';
	public const PARAM_POST = 'post';
	/** The pagination parameter; `page` is taken by the PRADO page service */
	public const PARAM_PAGE = 'pg';
	public const PARAM_MEMBER = 'member';
	public const PARAM_TAG = 'tag';
	public const PARAM_QUERY = 'q';
	public const PARAM_TYPE = 'type';
	public const PARAM_ATTACHMENT = 'attachment';

	/** @var \WeakReference<BEForumModule> the module */
	private \WeakReference $_module;

	/** @var array<string, string> page names keyed by role */
	private array $_pages = [
		'index' => 'Index',
		'board' => 'Board',
		'thread' => 'Thread',
		'newThread' => 'NewThread',
		'editPost' => 'EditPost',
		'search' => 'Search',
		'tag' => 'Tag',
		'member' => 'Member',
		'members' => 'Members',
		'notifications' => 'Notifications',
		'subscriptions' => 'Subscriptions',
		'bookmarks' => 'Bookmarks',
		'attachment' => 'Attachment',
		'adminStructure' => 'Admin.Structure',
		'adminModeration' => 'Admin.Moderation',
		'adminMembers' => 'Admin.Members',
	];

	/**
	 * @param BEForumModule $module the module
	 */
	public function __construct(BEForumModule $module)
	{
		$this->_module = \WeakReference::create($module);
		parent::__construct();
	}

	/**
	 * @return BEForumModule the module
	 */
	public function getModule(): BEForumModule
	{
		return $this->_module->get();
	}

	/**
	 * @param string $role the page role (index, board, thread, ...)
	 * @return string the page path including the prefix
	 */
	public function getPagePath(string $role): string
	{
		$name = $this->_pages[$role] ?? ucfirst($role);
		$prefix = $this->getModule()->getPagePathPrefix();
		return $prefix === '' ? $name : $prefix . '.' . $name;
	}

	/**
	 * Remaps a page role to another page name.
	 * @param string $role the page role
	 * @param string $name the page name without prefix
	 */
	public function setPageName(string $role, string $name): void
	{
		$this->_pages[$role] = TPropertyValue::ensureString($name);
	}

	/**
	 * @return array<string, string> the page names keyed by role
	 */
	public function getPageNames(): array
	{
		return $this->_pages;
	}

	/**
	 * Builds a page URL.
	 * @param string $pagePath the full page path
	 * @param array $params the GET parameters, null values are dropped
	 * @param null|string $anchor an optional fragment
	 * @return string the URL
	 */
	public function build(string $pagePath, array $params = [], ?string $anchor = null): string
	{
		$params = array_filter($params, fn ($value) => $value !== null && $value !== '');
		$url = '';
		$app = Prado::getApplication();
		if ($app !== null && ($request = $app->getRequest()) !== null) {
			$url = $request->constructUrl($app->getPageServiceID(), $pagePath, $params, false, true);
		} else {
			$url = '?page=' . $pagePath . ($params ? '&' . http_build_query($params) : '');
		}
		if ($anchor !== null && $anchor !== '') {
			$url .= '#' . $anchor;
		}
		return $this->dyBuildUrl($url, $pagePath, $params, $anchor);
	}

	/**
	 * Makes a URL absolute using the request base URL.
	 * @param string $url a relative or absolute URL
	 * @return string the absolute URL
	 */
	public function absolute(string $url): string
	{
		if (preg_match('#^https?://#i', $url)) {
			return $url;
		}
		$app = Prado::getApplication();
		if ($app !== null && ($request = $app->getRequest()) !== null) {
			$base = $request->getBaseUrl();
			if ($url === '' || $url[0] !== '/') {
				$url = '/' . ltrim($url, '/');
			}
			return rtrim($base, '/') . $url;
		}
		return $url;
	}

	/**
	 * @param int $page a 1-based page
	 * @return null|int the page parameter, null for the first page
	 */
	protected function pageParam(int $page): ?int
	{
		return $page > 1 ? $page : null;
	}

	/**
	 * @return string the forum index URL
	 */
	public function index(): string
	{
		return $this->build($this->getPagePath('index'));
	}

	/**
	 * @param BEForumBoard|int $board the board or its id
	 * @param int $page the 1-based page
	 * @return string the board URL
	 */
	public function board($board, int $page = 1): string
	{
		return $this->build($this->getPagePath('board'), [self::PARAM_BOARD => $this->idOf($board), self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param BEForumThread|int $thread the thread or its id
	 * @param int $page the 1-based page
	 * @param null|int $postId a post to jump to
	 * @return string the thread URL
	 */
	public function thread($thread, int $page = 1, ?int $postId = null): string
	{
		return $this->build($this->getPagePath('thread'), [self::PARAM_THREAD => $this->idOf($thread), self::PARAM_PAGE => $this->pageParam($page)], $postId ? 'post-' . $postId : null);
	}

	/**
	 * @param BEForumPost $post the post
	 * @return string the URL of the thread page containing the post, with the post anchor
	 */
	public function post(BEForumPost $post): string
	{
		$page = $this->getModule()->getPosts()->getPageOfPost($post);
		return $this->thread((int) $post->thread_id, $page, $post->getId());
	}

	/**
	 * @param null|BEForumPost|int $post the post or its id
	 * @return string the URL of the post page (thread page resolved by the post id)
	 */
	public function postById($post): string
	{
		return $this->build($this->getPagePath('thread'), [self::PARAM_POST => $this->idOf($post)]);
	}

	/**
	 * @param BEForumBoard|int $board the board or its id
	 * @return string the new thread URL
	 */
	public function newThread($board): string
	{
		return $this->build($this->getPagePath('newThread'), [self::PARAM_BOARD => $this->idOf($board)]);
	}

	/**
	 * @param BEForumPost|int $post the post or its id
	 * @return string the edit post URL
	 */
	public function editPost($post): string
	{
		return $this->build($this->getPagePath('editPost'), [self::PARAM_POST => $this->idOf($post)]);
	}

	/**
	 * @param string $query the search query
	 * @param null|BEForumBoard|int $board an optional board
	 * @param int $page the 1-based page
	 * @param null|string $type threads or posts
	 * @return string the search URL
	 */
	public function search(string $query = '', $board = null, int $page = 1, ?string $type = null): string
	{
		return $this->build($this->getPagePath('search'), [
			self::PARAM_QUERY => $query,
			self::PARAM_BOARD => $board === null ? null : $this->idOf($board),
			self::PARAM_TYPE => $type,
			self::PARAM_PAGE => $this->pageParam($page),
		]);
	}

	/**
	 * @param BEForumTag|string $tag the tag or its slug
	 * @param int $page the 1-based page
	 * @return string the tag URL
	 */
	public function tag($tag, int $page = 1): string
	{
		$slug = $tag instanceof BEForumTag ? $tag->slug : (string) $tag;
		return $this->build($this->getPagePath('tag'), [self::PARAM_TAG => $slug, self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param BEForumMember|string $member the member or its username
	 * @return string the member profile URL
	 */
	public function member($member): string
	{
		$name = $member instanceof BEForumMember ? $member->username : (string) $member;
		return $this->build($this->getPagePath('member'), [self::PARAM_MEMBER => $name]);
	}

	/**
	 * @param int $page the 1-based page
	 * @return string the member list URL
	 */
	public function members(int $page = 1): string
	{
		return $this->build($this->getPagePath('members'), [self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param int $page the 1-based page
	 * @return string the notifications URL
	 */
	public function notifications(int $page = 1): string
	{
		return $this->build($this->getPagePath('notifications'), [self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param int $page the 1-based page
	 * @return string the subscriptions URL
	 */
	public function subscriptions(int $page = 1): string
	{
		return $this->build($this->getPagePath('subscriptions'), [self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param int $page the 1-based page
	 * @return string the bookmarks URL
	 */
	public function bookmarks(int $page = 1): string
	{
		return $this->build($this->getPagePath('bookmarks'), [self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param \Belisoful\Forum\Data\BEForumAttachment|int $attachment the attachment or its id
	 * @return string the attachment download URL
	 */
	public function attachment($attachment): string
	{
		return $this->build($this->getPagePath('attachment'), [self::PARAM_ATTACHMENT => $this->idOf($attachment)]);
	}

	/**
	 * @return string the structure administration URL
	 */
	public function adminStructure(): string
	{
		return $this->build($this->getPagePath('adminStructure'));
	}

	/**
	 * @param int $page the 1-based page
	 * @return string the moderation administration URL
	 */
	public function adminModeration(int $page = 1): string
	{
		return $this->build($this->getPagePath('adminModeration'), [self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param int $page the 1-based page
	 * @return string the member administration URL
	 */
	public function adminMembers(int $page = 1): string
	{
		return $this->build($this->getPagePath('adminMembers'), [self::PARAM_PAGE => $this->pageParam($page)]);
	}

	/**
	 * @param mixed $record a record or an id
	 * @return null|int the id
	 */
	protected function idOf($record): ?int
	{
		if ($record === null) {
			return null;
		}
		if (is_object($record) && method_exists($record, 'getId')) {
			return $record->getId();
		}
		return (int) $record;
	}
}
