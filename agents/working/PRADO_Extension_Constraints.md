# PRADO 4.3 constraints for composer extensions (verified in vendor/pradosoft/prado/framework)

## Plugin modules
- `TPluginModule::init()` attaches `onAdditionalPagePaths` to the page service so `?page=Forum.Thread` resolves to `<PluginPath>/Pages/Forum/Thread.page`. The plugin path is the directory of the module class file (`src/`).
- `TPluginModule::getErrorFile()` registers `<PluginPath>/errorMessages.txt` with `TException::addMessageFile()`; keys are looked up globally by every `TException`.
- `TPageService::createPage()` rejects additional page paths that are not under the `Application` alias (`THttpException 403 pageservice_security_violation`). Hosts whose `vendor/` sits outside the application base path must set `PluginPagesPath` to a copy of the pages inside the application, or build their own pages from the template controls.
- `TPageService::createPage()` only resolves page classes named after the file basename in the global namespace or under `Application\Pages\`. Therefore extension pages ship as template-only `.page` files.
- `TControl::getPluginModule()` finds the plugin module whose `PluginPath` contains the control's class file. All BEForum controls use it to locate `BEForumModule`.
- `TTemplateManager::getTemplateByClassName()` loads `<class dir>/<ShortName>.tpl`; works anywhere in vendor.
- `TDbPluginModule::getDbConnection()` uses `ConnectionID` (a `TDataSourceConfig` module) or falls back to a SQLite file in the runtime path named by `getSqliteDatabaseName()`.

## Active Record
- Table name resolution: constant `TABLE` first, then method `table()`, then lowercase class name. `BEForumRecord::table()` prepends the configured prefix; records must not define `TABLE`.
- `TActiveRecord::getDbConnection()` falls back to `TActiveRecordManager::getInstance()->getDbConnection()`. `BEForumRecord` overrides it to use the forum module connection so the host application's AR configuration is untouched.
- Relation finders are created with `TActiveRecord::finder($class)`; `finder()` caches one instance per class.
- `TActiveRecordGateway::getCommand()` caches the command builder **per connection string together with the first `TDbConnection` object** it saw. Creating a second `TDbConnection` with the same DSN (tests, reconfiguration) makes record writes use the first object's session while raw commands use the second: two database sessions, broken transactions and row-lock deadlocks (`SELECT ... FOR UPDATE`). Tests therefore share one connection per external DSN and use a unique SQLite file per test.
- `$RELATIONS` entries: `[self::BELONGS_TO, Class::class, 'fk_column']`, `[self::HAS_MANY, Class::class, 'fk_column']`.
- `TSqlCriteria`: `new TActiveRecordCriteria('cond', [params])`, `->OrdersBy`, `->Limit`, `->Offset`. `count()`, `findAll()`, `find()`, `findByPk()`, `findAllByPks()`.
- Save raises `OnInsert`/`OnUpdate`/`OnDelete` events with `TActiveRecordChangeEventParameter` (`IsValid`).

## Permissions
- `TPermissionsManager::init()` attaches `TPermissionsBehavior` (class behavior) to every `IPermissions` and `TUserPermissionsBehavior` to every `IUser` (`$user->can($perm, $extra)`).
- `IPermissions::getPermissions($manager)` returns `TPermissionEvent[]` (name, description, dy events, preset rules). Listed dy events are intercepted: the behavior returns `true` (handled/denied) when `$user->can()` fails. Extra data is the last argument when it is an array with key `extra`.
- `TAuthorizationRule($action, $users, $roles, $verb, $ips, $priority)`: users `*`, `?` (guest), `@` (authenticated) or names. `TUserOwnerRule` allows when `$extra['username']` equals the user name.
- `TAuthorizationRuleCollection::isUserAllowed($user, $verb, $ip, $extra)` returns true when no rule decides.
- Without a manager, `$this->dyX(false, ...)` returns `false` (allowed); `BEForumModule::authorize()` therefore also evaluates the preset rules itself.

## Shell, cron, feeds, json
- Modules register shell actions in `onAuthenticationComplete`: `if ($this->dyRegisterShellAction(false) !== true && $app instanceof TShellApplication) $app->addShellActionClass([...])`.
- `TShellAction`: `$action`, `$methods`, `$parameters`, `$optional`, `$description` (first entry is the general description), methods `actionXxx($args)`, writer via `getWriter()`.
- `TCronModule::getTaskInfos()` raises the global event `fxGetCronTaskInfos`; modules answer with `TCronTaskInfo($name, 'moduleId->method', $moduleId, $title, $description)`. `TCronMethodTask` executes `module->method(args)`.
- `TFeedService` creates the configured class, sets properties, calls `init($config)`, then `getFeedContent()` and `getContentType()`.
- `TJsonService` uses `TJsonResponse::getJsonContent()`.

## Templates
- Expressions: `<%= expr %>`, data binding `<%# expr %>`, parameter `<%$ name %>`, asset `<%~ path %>`, localization `<%[ text ]%>`, statements `<%% %>`.
- `<com:Class>` with `<prop:Name>` sub-templates; `Template`-suffixed properties (ItemTemplate) are parsed as templates.
- `TConditional` evaluates `Condition` before `onInit`; avoid it for data-dependent branches, use `Visible` binding instead.
