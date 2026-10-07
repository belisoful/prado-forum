# Open items

- Reactions, subscriptions and bookmarks use full postbacks; converting `BEForumReactionBar`, `BEForumSubscribeButton` and the bookmark action to `TActive*` callbacks would avoid page reloads.
- The built-in search is `LIKE` based; a MySQL FULLTEXT / PostgreSQL tsvector backend can be plugged in through `dySearchCriteria` and shipped as an optional behavior.
- E-mail delivery of notifications is left to the host (`onNotification` event); a reference `TBehavior` sending mail would be a useful example.
- `TPermissionsManager` registers each permission once per application; with several `BEForumModule` instances the first initialized instance defines the preset rules (`BEForumRoleRule` merges the admin/moderator lists of all initialized modules).
- PRADO only serves plugin pages located under the application path; document or contribute a framework change so `vendor/` pages can be allowed explicitly.
- Thread split/merge and post moving between threads are not implemented.
- A `TUrlMapping` example configuration for pretty forum URLs belongs in the README once agreed on a scheme.
- `agents/working/legacy-generated-src/` holds the superseded generated code and may be deleted.
- Postback handlers of the controls (`*Clicked`, `*Command`) are only covered indirectly; add page lifecycle tests that raise the postback events.
- Decide whether a deployable forum application (master layout, `TUrlMapping` routes, authentication, the shipped `.page` files wired up) should live in its own repository; this repository stays the controls/managers library, and its template-only pages are reference compositions.
- PRADO 4.3.2 `TPgsqlMetaData` triggers a PHP 8.1 deprecation on columns without a default; the test bootstrap mutes `E_DEPRECATED` for PostgreSQL until PRADO 4.3.3.
