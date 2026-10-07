# PRADO Forum

A complete, template-control driven forum for [PRADO 4.3](https://github.com/pradosoft/prado) applications, installed through Composer as a `prado4-extension`.

The forum is built the PRADO way: one `TDbPluginModule` (`BEForumModule`) owns the configuration, the database and the domain managers; every piece of UI is a `TTemplateControl` you can drop on any page; pages are plain `.page` templates composed from those controls; authorization runs through PRADO's `TPermissionsManager` and `TAuthorizationRule`s; and every decision point is a `dy*` dynamic event so behaviors can extend or replace forum logic without touching the extension.

## Features

- Categories, nested boards (locked, hidden, members-only), threads and posts with denormalised counters and last-post tracking
- Thread types (discussion, question with accepted answer, announcement), pinning with expiry, locking, moving, soft deletion and restore, permanent purge
- Markdown (safe mode) and plain text content, rendered through HTMLPurifier, with `@mention` links, quoting and preview
- Edit history with revisions, edit windows, flood control, approval queue for new content
- Reactions (configurable set), polls (single or multiple choice, closing time, revote), tags and tag cloud, attachments with type and size limits
- Subscriptions to boards and threads, in-forum notifications (replies, mentions, quotes, reactions, accepted answers, moderation, badges, reports), bookmarks, read tracking with unread markers and "first unread post" links
- Search over posts and thread titles (LIKE based, replaceable through `dySearchCriteria`)
- Moderation: reports, moderation log, warnings, temporary and permanent bans, board moderators, badges
- Member profiles (display name, avatar/gravatar, signature, bio, timezone, notification preferences), member directory, statistics
- RSS/Atom feeds (`TFeedService`), a read-only JSON API (`TJsonService`), `prado-cli forum/*` shell commands, cron maintenance tasks (`TCronModule`)
- SQLite (zero configuration), MySQL/MariaDB and PostgreSQL through one driver-neutral schema with versioned migrations
- Localizable through `Prado::localize`, themable through a CSS class prefix and an optional bundled stylesheet

## Requirements

- PHP 8.1 or higher with `pdo`, `json`, `mbstring`, `intl`, `dom` and one of `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`
- PRADO 4.3 or higher

## Installation

```bash
composer require belisoful/prado-forum
```

## Configuration

Add the module to the application configuration (XML shown; PHP configuration works the same way). The module is the bootstrap class declared in `composer.json`, so the module id may also be the package name.

```xml
<modules>
  <module id="db" class="Prado\Data\TDataSourceConfig">
    <database ConnectionString="mysql:host=localhost;dbname=app;charset=utf8mb4" Username="app" Password="secret" />
  </module>
  <module id="forum" class="Belisoful\Forum\BEForumModule"
      ConnectionID="db"
      Title="Community"
      AdminUsers="admin"
      ModeratorRoles="Moderators" />
</modules>
<parameters>
  <parameter id="PluginContentId" value="Main" />
</parameters>
```

Without `ConnectionID` the forum creates `beforum.db` (SQLite) in the runtime directory. With `AutoInstall` (the default) the tables are created and upgraded on first use; `php prado-cli.php forum/install` does the same from the shell and `forum/seed` creates a first category and board.

The pages ship with `<com:TContent ID=<%$ PluginContentId %>>`, the PRADO plugin convention: the page service must set a `MasterClass` whose layout provides a `TContentPlaceHolder` with that id, a `<com:THead />` and a `<com:TForm>` (without a master, PRADO throws `templatecontrol_placeholder_inexistent`).

### Module properties

| Property | Default | Purpose |
| --- | --- | --- |
| `ConnectionID` | | `TDataSourceConfig` module id; empty uses SQLite in the runtime path |
| `TablePrefix` | `forum_` | Table name prefix (set before initialization) |
| `Title` | `Forum` | Forum name used in titles and feeds |
| `PagePathPrefix` | `Forum` | Page path prefix of the shipped pages (`Forum.Index`, `Forum.Thread`, ...) |
| `AdminUsers`, `AdminRoles` | | Users/roles holding every forum permission |
| `ModeratorUsers`, `ModeratorRoles` | | Users/roles holding the moderation permission everywhere |
| `ThreadsPerPage`, `PostsPerPage`, `ItemsPerPage` | 25, 20, 20 | Page sizes |
| `ContentFormat` | `markdown` | Default content format (`markdown` or `text`) |
| `EditWindow` | 0 | Seconds an owner may edit a post, 0 for unlimited |
| `FloodInterval` | 30 | Minimum seconds between posts of a member, 0 disables |
| `MaxTitleLength`, `MinPostLength`, `MaxPostLength`, `MaxTagsPerThread` | 255, 1, 65535, 5 | Validation limits |
| `EnablePolls`, `EnableAttachments`, `EnableReactions`, `EnableTags`, `EnableSubscriptions`, `EnableSignatures`, `EnableBookmarks`, `EnableBadges` | true | Feature switches |
| `RequireApproval` | false | New threads and replies of non-moderators await approval |
| `ReactionTypes` | `like,love,laugh,wow,sad` | Available reactions |
| `AttachmentPath`, `AttachmentMaxSize`, `AttachmentTypes` | runtime path, 2 MB, `png,jpg,jpeg,gif,webp,pdf,txt,zip` | Attachment storage and limits |
| `AutoCreateMembers` | true | Create a member profile for every authenticated user on first contact |
| `AutoInstall` | true | Install and upgrade the schema automatically |
| `CssClassPrefix`, `EnableDefaultStyles` | `beforum`, true | CSS class prefix of the controls and the bundled stylesheet |
| `DateFormat`, `Timezone`, `GuestName` | `M j, Y H:i`, UTC, `Guest` | Display settings |
| `SearchMinLength`, `NotificationRetentionDays` | 3, 90 | Search and maintenance settings |
| `RendererClass`, `UrlBuilderClass`, `ShellActionClass` | | Replace the content renderer, URL builder or shell action with subclasses |

## Pages and controls

The extension ships template-only pages under `src/Pages/Forum` (`Index`, `Board`, `Thread`, `NewThread`, `EditPost`, `Search`, `Tag`, `Member`, `Members`, `Notifications`, `Subscriptions`, `Bookmarks`, `Attachment`, `Admin.Structure`, `Admin.Moderation`, `Admin.Members`). They are reachable as `?page=Forum.Thread&thread=12` (pages are `&pg=2`) and the like; with `TUrlMapping` the URLs become pretty without any forum change.

PRADO only serves plugin pages that live under the application directory. If your `vendor/` directory is outside it (the usual layout), either point the module at a copy of the pages inside your application (`PluginPagesPath`) or build your own pages from the controls, which is the intended way to integrate the forum into a site:

```xml
<com:TContent ID="Main">
  <com:Belisoful\Forum\Web\UI\BEForumToolbar />
  <com:Belisoful\Forum\Web\UI\BEForumBreadcrumbs BoardID=<%= $this->Request['board'] %> />
  <com:Belisoful\Forum\Web\UI\BEForumBoardHeader BoardID=<%= $this->Request['board'] %> />
  <com:Belisoful\Forum\Web\UI\BEForumThreadList BoardID=<%= $this->Request['board'] %> PageSize="15" />
</com:TContent>
```

Controls (namespace `Belisoful\Forum\Web\UI`): `BEForumToolbar`, `BEForumBreadcrumbs`, `BEForumSearchBox`, `BEForumCategoryList`, `BEForumBoardHeader`, `BEForumThreadList`, `BEForumThreadView`, `BEForumPostList`, `BEForumPostView`, `BEForumPostEditor`, `BEForumThreadEditor`, `BEForumPoll`, `BEForumReactionBar`, `BEForumSubscribeButton`, `BEForumModerationTools`, `BEForumSearchResults`, `BEForumTagCloud`, `BEForumRecentPosts`, `BEForumStatistics`, `BEForumMemberCard`, `BEForumMemberProfile`, `BEForumProfileEditor`, `BEForumMemberList`, `BEForumNotificationList`, `BEForumSubscriptionList`, `BEForumBookmarkList`, `BEForumAttachmentDownload`, `BEForumAdminStructure`, `BEForumAdminModeration`, `BEForumAdminMembers`, plus the `BEForumPager` and the item renderers `BEForumBoardRow`, `BEForumThreadRow`.

Every control finds the forum module through PRADO's plugin lookup; set `ModuleID` when several forums exist. Templates only render pre-built, HTML escaped view models: all logic lives in the managers.

## Permissions

Permission names live in `Belisoful\Forum\Security\BEForumPermissions`: `forum_view`, `forum_thread_create`, `forum_thread_edit`, `forum_thread_delete`, `forum_post_create`, `forum_post_edit`, `forum_post_delete`, `forum_react`, `forum_poll_create`, `forum_poll_vote`, `forum_attach`, `forum_report`, `forum_subscribe`, `forum_bookmark`, `forum_profile_edit`, `forum_moderate`, `forum_admin` and `forum_shell`.

Without a `TPermissionsManager` sensible preset rules apply: everyone reads, authenticated users write, owners edit their own content, board moderators and the configured `ModeratorUsers`/`ModeratorRoles` moderate, `AdminUsers`/`AdminRoles` administer.

With a `TPermissionsManager` the module registers the same permissions (with the preset rules) and the manager's roles and rules take over:

```xml
<module id="permissions" class="Prado\Security\Permissions\TPermissionsManager" DefaultRoles="Default">
  <role name="ForumAdministrator" children="forum_admin, forum_moderate" />
  <role name="ForumModerator" children="forum_moderate" />
  <role name="Default" children="forum_view, forum_thread_create, forum_post_create, forum_react, forum_poll_vote, forum_attach, forum_report, forum_subscribe, forum_bookmark, forum_profile_edit" />
  <permissionrule name="forum_thread_create" action="deny" users="?" />
</module>
```

Application users get a class behavior: `$this->User->getForumMember()`, `getForumDisplayName()` and `forumCan('forum_moderate')`.

## Feeds, JSON, shell and cron

```xml
<services>
  <service id="feed" class="Prado\Web\Services\TFeedService">
    <feed id="forum" class="Belisoful\Forum\Feeds\BEForumFeed" />
    <feed id="forum-posts" class="Belisoful\Forum\Feeds\BEForumFeed" Scope="posts" Format="atom" />
  </service>
  <service id="json" class="Prado\Web\Services\TJsonService">
    <json id="forum" class="Belisoful\Forum\Feeds\BEForumJsonResponse" />
  </service>
</services>
```

`?feed=forum&board=3`, `?feed=forum-posts&thread=7`, `?json=forum&action=threads|posts|search|tags|stats`.

Shell: `php prado-cli.php forum/status|install|upgrade|seed|recount|rerender|maintenance|ddl [driver]`.

Cron: the module advertises `forum_maintenance` (expired bans and pins, old notifications, unused tags) to `TCronModule`; schedule the recount separately as a `forum->recountStatistics` method task.

## Extending the forum

- **Events**: the module raises `onThreadCreated`, `onPostCreated`, `onPostUpdated`, `onPostDeleted`, `onReaction`, `onPollVoted`, `onReportCreated`, `onNotification`, `onMemberBanned`, ... with a `BEForumEventParameter` (record, actor, data). Attach handlers in a behavior or module: `$forum->onNotification[] = [$mailer, 'send'];`
- **Dynamic events**: attach behaviors to the module or its managers to filter decisions: `dyAuthorize`, `dyCreateManager`, `dyCreateRenderer`, `dyBuildUrl`, `dyValidateThread`, `dyValidatePost`, `dyEditWindow`, `dyNotify`, `dySearchCriteria`, `dyAllowedAttachment`, `dyReactionReputation`, `dyRenderContent`, `dyRenderFormat` (add a content format), `dyCreateMember`, `dyValidateProfile`, `dyIsThreadVisible`, `dyIsPostVisible`, `dyCronTaskInfos`.
- **Managers**: subclass a manager and register it with `setManager()` or the `dyCreateManager` event; all business rules live there (`getThreads()`, `getPosts()`, `getMembers()`, `getBoards()`, `getReactions()`, `getPolls()`, `getTags()`, `getAttachments()`, `getSubscriptions()`, `getNotifications()`, `getSearch()`, `getModeration()`, `getReadTracker()`, `getBookmarks()`, `getStatistics()`).
- **Records**: `Belisoful\Forum\Data\BEForum*` Active Records raise PRADO's `OnInsert`/`OnUpdate`/`OnDelete` events and accept class behaviors.
- **Templates**: copy the pages, recompose the controls, override `CssClassPrefix`, or disable the bundled stylesheet.

## Development

```bash
composer install
vendor/bin/php-cs-fixer fix --dry-run
vendor/bin/phpstan analyse --memory-limit=512M
composer unittest
```

The unit tests run on SQLite by default. Set `BEFORUM_TEST_DSN`, `BEFORUM_TEST_USER` and `BEFORUM_TEST_PASSWORD` to run the same suite against MySQL or PostgreSQL (the schema is recreated for every test).

## License

BSD-3-Clause, see [LICENSE](LICENSE).

## Author

Brad Anderson (belisoful@icloud.com)
