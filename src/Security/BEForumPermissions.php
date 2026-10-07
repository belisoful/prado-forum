<?php

/**
 * BEForumPermissions class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Security;

use Prado\Security\Permissions\TUserOwnerRule;
use Prado\Security\TAuthorizationRule;

/**
 * BEForumPermissions class.
 *
 * BEForumPermissions declares every permission name used by the forum, the
 * dynamic event that guards it, a description and the preset authorization rules
 * applied when the {@see \Prado\Security\Permissions\TPermissionsManager} property
 * `AutoPresetRules` is enabled (the default) or when no permissions manager has
 * been installed at all.
 *
 * Permission names are `forum_*` role-like identifiers.  Hosts grant them by
 * putting them in a role hierarchy, for example:
 * ```xml
 * <module id="permissions" class="Prado\Security\Permissions\TPermissionsManager" DefaultRoles="Default">
 *   <role name="ForumAdministrator" children="forum_admin, forum_moderate" />
 *   <role name="ForumModerator" children="forum_moderate" />
 *   <role name="Default" children="forum_view, forum_thread_create, forum_post_create, forum_react, forum_poll_vote, forum_attach, forum_report, forum_subscribe, forum_bookmark, forum_profile_edit" />
 * </module>
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
final class BEForumPermissions
{
	/** Viewing categories, boards, threads and posts */
	public const VIEW = 'forum_view';
	/** Creating a new thread in a board */
	public const THREAD_CREATE = 'forum_thread_create';
	/** Editing a thread title, tags and type; owners are allowed by preset */
	public const THREAD_EDIT = 'forum_thread_edit';
	/** Deleting a thread; owners are allowed by preset */
	public const THREAD_DELETE = 'forum_thread_delete';
	/** Replying in a thread */
	public const POST_CREATE = 'forum_post_create';
	/** Editing a post; owners are allowed by preset */
	public const POST_EDIT = 'forum_post_edit';
	/** Deleting a post; owners are allowed by preset */
	public const POST_DELETE = 'forum_post_delete';
	/** Reacting to a post */
	public const REACT = 'forum_react';
	/** Attaching a poll to a new thread */
	public const POLL_CREATE = 'forum_poll_create';
	/** Voting in a poll */
	public const POLL_VOTE = 'forum_poll_vote';
	/** Uploading attachments */
	public const ATTACH = 'forum_attach';
	/** Reporting a post to moderators */
	public const REPORT = 'forum_report';
	/** Subscribing to threads and boards */
	public const SUBSCRIBE = 'forum_subscribe';
	/** Bookmarking posts */
	public const BOOKMARK = 'forum_bookmark';
	/** Editing the own forum profile; owners are allowed by preset */
	public const PROFILE_EDIT = 'forum_profile_edit';
	/** Moderating: lock, pin, move, approve, delete anything, handle reports, warn and ban members */
	public const MODERATE = 'forum_moderate';
	/** Administering: categories, boards, moderators */
	public const ADMIN = 'forum_admin';
	/** Using the forum shell commands */
	public const SHELL = 'forum_shell';

	/**
	 * @return array<string, array{event: string, description: string, rules: callable}> the permission definitions keyed by name
	 */
	public static function getDefinitions(): array
	{
		return [
			self::VIEW => [
				'event' => 'dyViewForum',
				'description' => 'View forum categories, boards, threads and posts.',
				'rules' => fn () => [new TAuthorizationRule('allow', '*', '*')],
			],
			self::THREAD_CREATE => [
				'event' => 'dyCreateThread',
				'description' => 'Create new forum threads.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::THREAD_EDIT => [
				'event' => 'dyEditThread',
				'description' => 'Edit forum thread titles, tags and types.',
				'rules' => fn () => [new BEForumModeratorRule(), new TUserOwnerRule()],
			],
			self::THREAD_DELETE => [
				'event' => 'dyDeleteThread',
				'description' => 'Delete forum threads.',
				'rules' => fn () => [new BEForumModeratorRule(), new TUserOwnerRule()],
			],
			self::POST_CREATE => [
				'event' => 'dyCreatePost',
				'description' => 'Reply to forum threads.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::POST_EDIT => [
				'event' => 'dyEditPost',
				'description' => 'Edit forum posts.',
				'rules' => fn () => [new BEForumModeratorRule(), new TUserOwnerRule()],
			],
			self::POST_DELETE => [
				'event' => 'dyDeletePost',
				'description' => 'Delete forum posts.',
				'rules' => fn () => [new BEForumModeratorRule(), new TUserOwnerRule()],
			],
			self::REACT => [
				'event' => 'dyReact',
				'description' => 'React to forum posts.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::POLL_CREATE => [
				'event' => 'dyCreatePoll',
				'description' => 'Attach polls to forum threads.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::POLL_VOTE => [
				'event' => 'dyVotePoll',
				'description' => 'Vote in forum polls.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::ATTACH => [
				'event' => 'dyAttachFile',
				'description' => 'Upload forum attachments.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::REPORT => [
				'event' => 'dyReportPost',
				'description' => 'Report forum posts to moderators.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::SUBSCRIBE => [
				'event' => 'dySubscribe',
				'description' => 'Subscribe to forum boards and threads.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::BOOKMARK => [
				'event' => 'dyBookmark',
				'description' => 'Bookmark forum posts.',
				'rules' => fn () => [new TAuthorizationRule('allow', '@', '*')],
			],
			self::PROFILE_EDIT => [
				'event' => 'dyEditProfile',
				'description' => 'Edit forum member profiles.',
				'rules' => fn () => [new TUserOwnerRule()],
			],
			self::MODERATE => [
				'event' => 'dyModerate',
				'description' => 'Moderate forum content and members.',
				'rules' => fn () => [new BEForumModeratorRule()],
			],
			self::ADMIN => [
				'event' => 'dyAdministrate',
				'description' => 'Administer forum categories, boards and moderators.',
				'rules' => fn () => [],
			],
			self::SHELL => [
				'event' => 'dyRegisterShellAction',
				'description' => 'Use the forum shell commands.',
				'rules' => fn () => [],
			],
		];
	}

	/**
	 * @return string[] all permission names
	 */
	public static function getNames(): array
	{
		return array_keys(self::getDefinitions());
	}

	/**
	 * @param string $permission the permission name
	 * @return null|string the dynamic event guarding the permission
	 */
	public static function getEvent(string $permission): ?string
	{
		return self::getDefinitions()[strtolower($permission)]['event'] ?? null;
	}

	/**
	 * @param string $permission the permission name
	 * @return TAuthorizationRule[] fresh preset rules for the permission
	 */
	public static function getPresetRules(string $permission): array
	{
		$definition = self::getDefinitions()[strtolower($permission)] ?? null;
		return $definition ? $definition['rules']() : [];
	}
}
