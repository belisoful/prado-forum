# BEForum architecture (Belisoful\Forum, version 0.1.0)

## Layers
1. **BEForumModule** (`src/BEForumModule.php`, `TDbPluginModule` + `IPermissions`): the single configuration point. Owns the DB connection, table prefix, feature flags, page path prefix, managers, URL builder, content renderer, permission definitions, the user behavior, shell action and cron task advertisement. Raises `on*` events for every domain change (`onThreadCreated`, `onPostCreated`, ...) and calls `dy*` events at every extension point.
2. **Data** (`src/Data/`): `BEForumSchema` declares every table in a driver-neutral DSL and emits DDL for sqlite/mysql/pgsql, installs and upgrades (versioned migrations). `BEForumRecord` is the Active Record base (prefix-aware `table()`, forum connection, timestamps, JSON columns, boolean accessors, dy events). One record class per table.
3. **Managers** (`src/Managers/`): all domain logic. `BEForumManager` base gives module/user/member/authorization/transaction helpers. Managers: Member, Thread, Post, Reaction, Poll, Tag, Attachment, Subscription, Notification, Search, Moderation, ReadTracker, Statistics. Managers throw `BEForumValidationException` for user errors and `BEForumForbiddenException`/`BEForumNotFoundException` for access/data errors.
4. **Content** (`src/Content/`): `BEForumContentRenderer` turns raw Markdown/plain text into sanitized HTML (Parsedown safe mode + HTMLPurifier), linkifies @mentions, exposes `dyRenderContent`.
5. **Web/UI** (`src/Web/UI/`): `BEForumControl` (base `TTemplateControl`) and the template controls that drive the UI. Every control finds its module through `getPluginModule()`, exposes properties for composition, sets the page title where it is the primary control, and delegates every action to a manager.
6. **Pages** (`src/Pages/Forum/*.page`): template-only compositions of controls wrapped in `<com:TContent ID=<%$ PluginContentId %>>`.
7. **Integration**: `BEForumFeed` (RSS/Atom via `TFeedService`), `BEForumJsonResponse` (`TJsonService`), `BEForumShellAction` (`prado-cli forum/install|upgrade|status|reindex|seed`), cron task infos (`forum_maintenance`), `BEForumUserBehavior` on `IUser` (`getForumMember()`).

## Authorization
- Permission names live in `Belisoful\Forum\Security\BEForumPermissions` (`forum_view`, `forum_thread_create`, `forum_post_edit`, `forum_moderate`, `forum_admin`, ...).
- `BEForumModule::getPermissions()` returns `TPermissionEvent`s with preset rules (`TUserOwnerRule` for edits, `BEForumModeratorRule` for board moderators, `@` authenticated for posting, `*` for viewing).
- `BEForumModule::authorize($permission, $extra, $throw)`: raises the mapped dy event (intercepted by `TPermissionsBehavior` when a `TPermissionsManager` exists), falls back to evaluating the preset rules directly when no manager is installed, then filters through `dyAuthorize`.
- Recommended host roles: `ForumAdministrator` (forum_admin + forum_moderate), `ForumModerator` (forum_moderate), `Default` (view/post/react/...).

## Extension points
- Behaviors on `BEForumModule`: `dyCreateManager`, `dyAuthorize`, `dyRenderContent`, `dyBuildUrl`, `dyCreateMember`, `dyValidateThread`, `dyValidatePost`, `dyNotify`, `dySearchCriteria`, `dyAllowedAttachment`.
- `on*` events on the module: `onThreadCreated`, `onThreadUpdated`, `onThreadDeleted`, `onPostCreated`, `onPostUpdated`, `onPostDeleted`, `onPostApproved`, `onReaction`, `onPollVoted`, `onReportCreated`, `onMemberBanned`, `onNotification`.
- Records raise `OnInsert/OnUpdate/OnDelete` (PRADO AR) and behaviors may be attached per class with `TComponent::attachClassBehavior`.
- Templates: hosts copy `src/Pages/Forum` into their application and re-compose controls; controls expose `CssClass`, `PageSize`, ID properties.

## URL scheme (page paths, GET parameters)
- `Forum.Index`; `Forum.Board` (`board`, `page`); `Forum.Thread` (`thread`, `page`, `post`); `Forum.NewThread` (`board`); `Forum.EditPost` (`post`); `Forum.Search` (`q`, `board`, `page`); `Forum.Tag` (`tag`, `page`); `Forum.Member` (`member`); `Forum.Members`; `Forum.Notifications`; `Forum.Subscriptions`; `Forum.Bookmarks`; `Forum.Admin.Structure`; `Forum.Admin.Moderation`; `Forum.Admin.Members`.
- `BEForumUrlBuilder` centralizes construction; `dyBuildUrl($url, $pagePath, $params)` lets hosts rewrite.

## Static analysis conventions (phpstan level 2)
- Template child controls are typed by `@property \FQCN $ID` lines in the owning control's class docblock (one line per `ID="..."` in the `.tpl`); keep them in sync when a template changes.
- Inside repeater item handlers use `$param->getItem()->findControl('ID')` plus `instanceof TTextBox` / `instanceof TRepeater` guards; `$item->getData()` requires `$item instanceof TRepeaterItem` (the event parameter exposes `TControl`).
- `IUser` has no `can()`/`asa()`; `BEForumModule::canUser()` guards with `$user instanceof TComponent` and calls `can` through `call_user_func` so the behavior-provided method is not flagged.

## Audit decisions (2026-10-06)
- The pagination URL parameter is `pg` (`BEForumUrlBuilder::PARAM_PAGE`); `page` belongs to the PRADO page service.
- LIKE patterns are escaped with `!` and every LIKE carries `BEForumManager::LIKE_ESCAPE_CLAUSE`; counting a criteria goes through `countCriteria()` (no ORDER BY in COUNT queries).
- Public post listings (recent posts, member posts, search, counts, board recount) add `BEForumPostManager::publicThreadCondition()` so posts of deleted or pending threads never leak.
- Counters: soft deleting/restoring a thread moves the author's `thread_count`; purging a thread decrements the `post_count` of every counted post; `recount()` and settings writes are targeted UPDATEs (never a full `save()` of a possibly stale record).
- Messages with HTML placeholders use `th()` (escape first, substitute HTML afterwards); `te()` is only for plain text.
- Post rows are rebuilt at pre-render, so transient per post state (open report form, error) lives in `BEForumPostRowsTrait` (`openReportFor()`, `setPostError()`) and the renderer restores it in `bindChildRepeaters()`.
- `canUser(null, ...)` is a guest (`BEForumGuestUser`) in web requests; only the shell is allow-all.
- `fxGetCronTaskInfos` returns a single `TCronTaskInfo` (TCronModule expects one per handler).
- Attachments store the detected MIME type, require edit rights on the post, send `X-Content-Type-Options: nosniff` and never display SVG inline.
