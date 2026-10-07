# PRADO Forum Extension Agent Guidelines

## Build, Lint, and Test Commands

### Running Tests
- **All Unit Tests**: `composer unittest` (runs `vendor/bin/phpunit --testsuite unit`)
- **Test Filter**: `vendor/bin/phpunit --testsuite unit --filter <test function, class, or directory>`
- **Full Check Script**: `composer fulltest` (php-cs-fixer dry-run, phpstan, unit tests)

### Linting and Code Analysis
- **PHP compile**: `for f in $(git ls-files -co --exclude-standard 'src/*.php' 'tests/*.php'); do php -l $f; done`
- **PHPStan Analysis**: `vendor/bin/phpstan analyse --memory-limit=512M`
- **PHP CS Fixer (Dry-run)**: `vendor/bin/php-cs-fixer fix --dry-run` (check)
- **PHP CS Fixer (Fix)**: `vendor/bin/php-cs-fixer fix` (apply fixes)

### Build Commands
- **Install Dependencies**: `composer install` - installs all dependencies
- **Updating Dependencies**: `composer update` - updates all dependencies
- **Generate Documentation**: `composer gendoc` - generates API documentation

## Code Style Guidelines
- "if" has a statement block after
- Use php-cs-fixer to correct code styles

### PHP Coding Standards
- Follow PSR-4 autoloading standard
- All PHP files must begin with `<?php` tag (short open tags not allowed)
- Use 1 tab for indentations (no spaces)
- All class names must be in PascalCase
- All method names must be in camelCase
- All variable names must be in camelCase
- Constants must be in SCREAMING_SNAKE_CASE
- Use explicit return types for methods when possible
- All class properties must be declared with visibility modifiers (public, protected, private)

### Naming Conventions
- Class name prefix: `BEForum*` (e.g. `BEForumModule`, `BEForumThreadManager`)
- Method names: `camelCase` (e.g., `getComponent`)
- Variables: `camelCase` (e.g., `$componentName`)
- Constants: `SCREAMING_SNAKE_CASE` (e.g., `MAX_RETRY_COUNT`)
- Namespace: `Belisoful\Forum\{Directory}` (e.g., `Belisoful\Forum\Web\UI\BEForumThreadList`)
- Template file extension: ".tpl" (same directory and base name as the control class)
- Every template child control with an `ID` is declared in the control's class docblock as `@property \Fully\Qualified\Class $ID` so phpstan (level 2) can type `$this->ID` accesses
- Child controls of a repeater item are fetched with `$item->findControl('ID')` and an `instanceof` check, never `$item->ID`
- Web Page template file extension: ".page"; forum pages are template-only (no `.php` code-behind) and are composed from template controls
- Database tables are prefixed by `BEForumModule::TablePrefix` (default `forum_`); record classes declare `TABLE_NAME` without the prefix

### Documentation Standards
- All public methods must have PHPDoc comments with:
  - `@param` for parameters
  - `@return` for return values
  - `@throws` for exceptions
- Classes must have a clear and comprehensive docblock at the top with class description with:
  - Examples, where necessary
  - `@author` for attribution
  - `@since` for version
  - `@method` for dynamic events with prefix 'dy-'; which are called (on "$this->dy-") but not defined.
- Inline comments should be in English and start with `//`
- When documenting new methods or classes with "@since" use the next release version.
- All documentation should be written in present perfect tense
- All code examples open with "```" plus the language and close with "```"

### Error Handling
- Use try/catch blocks for operations that can fail
- Throw appropriate PRADO exceptions (`TInvalidDataValueException`, `TInvalidOperationException`, etc.) or forum exceptions from `src/Exceptions/`
- User input problems are reported with `BEForumValidationException` (already localized message); controls catch and display them
- Return false or null for methods that are designed to fail gracefully
- All methods should handle edge cases and validate input parameters
- Extension exceptions use error codes specified in `src/errorMessages.txt`; the file is purely for user information display. All keys are prefixed `forum_`.

### Imports and Includes
- Use PSR-4 autoloading - no manual includes required
- All framework classes are accessed via namespace prefixes
- Third-party libraries are loaded via Composer
- Use proper `use` statements for namespaces at the top of PHP files

### Framework Specific Guidelines
- All components inherit from `TComponent` base class
- `TComponent` has features for dynamic event and extension by attached Behaviors (__call, __callStatic), dynamic properties (__get, __set, __isset, __unset), __clone, __sleep, __wakeup, and _getZappableSleepProps
- Behaviors can be attached to any `TComponent` to alter its behavior and functionality.
- Use the event-driven programming model with events; like `onLoad`, `onInit`, `onPreRender`
- Methods with prefix 'dy' are dynamic events to call attached and active Behaviors; like 'dyShouldContinue', 'dyClone', and 'dyValidate'
- Called Dynamic Events must be documented in the class phpdoc with "@method"
- Dynamic events are implemented by attached behaviors not in the calling class
- The first parameter of a dynamic event is always filtered and returned.
- Methods with prefix 'fx' are global events that may or may not be automatically registered depending on getAutoGlobalListen(); like 'fxAttachClassBehavior'
- getAutoGlobalListen() is optimized by class hierarchy for utility and performance
- All events are raised in specified priority order
- Follow the TApplication Lifecycle: onInitComplete (at end of TApplication::initApplication) → onBeginRequest → onLoadState → onLoadStateComplete → onAuthentication → onAuthenticationComplete → onAuthorization → onAuthorizationComplete → onPreRunService → runService → onSaveState → onSaveStateComplete → onPreFlushOutput → flushOutput → onEndRequest or onError (both at end of TApplication::run)
- Follow the TPage Lifecycle (via TPageService::runPage): onPreInit → initRecursive → onInitComplete → loadPageState (POST/Callback) → processPostData (POST/Callback) → onPreLoad → loadRecursive → processPostData (POST/Callback) → raiseChangedEvents (POST/Callback) → raisePostBackEvent (POST-only) → processCallbackEvent (Callback-only) → onLoadComplete → preRenderRecursive  onPreRenderComplete → savePageState → onSaveStateComplete → renderControl (GET/POST) → renderCallbackResponse (Callback-only) → unloadRecursive
- XML and PHP is supported for application configuration
- TPageService::onPreRunPage gives PRADO Modules event access to the TPage Lifecycle before it runs
- UI controls are PHP classes with a ".tpl" TTemplate file with the same base name; business logic lives in `src/Managers/`, never in templates
- Data components use the `TActiveRecord` pattern; all records extend `BEForumRecord`
- Authorization goes through `BEForumModule::authorize()` which integrates `TPermissionsManager` (when installed) with preset `TAuthorizationRule`s
- All UI controls should have proper template support and state management
- Backward compatibility is required from version 1.0.0 onward; before 1.0.0 the API may change freely
- A full check consists of the 4 checks (in order): `php -l` compile, php-cs-fixer, phpstan, phpunit (all checks must pass successfully)
- A full check must be done for code to be ready for git commit.
- The current version is 0.1.0. git HEAD is working on version 0.1.0.

## Testing Guidelines
- The testing platform is "phpunit"
- Unit tests run against a private temporary SQLite database file per test created by `BEForumSchema` (or the `BEFORUM_TEST_DSN` database); `tests/test_tools/phpunit_bootstrap.php` creates the `TApplication` from `tests/app`
- `tests/unit/BEForumTestCase.php` is the base test case: it installs the schema, creates a `BEForumModule` and provides user/member helpers
- All new code must include unit tests
- Unit test functions must comprehensively assert both typical and edge cases
- Maximal coverage of code execution paths of a class is required
- Test error conditions and exception handling
- Use mock objects where appropriate
- Tests should be isolated from each other (no shared state)
- When unit testing one or cluster of classes, only run the unit tests for that class or cluster/directory.
- NEVER add/change phpunit command options when unit testing; only run project unit tests as specified

## Development Environment
- PHP 8.1 or higher required
- PHP extensions: ctype, dom, intl, json, mbstring, pcre, pdo, pdo_sqlite, spl (required)
- Optional extensions for additional features: apcu, openssl, pdo_mysql, pdo_pgsql, soap, xsl, zlib
- Composer for dependency management
- Required developer dependencies for code checking: phpunit/phpunit, phpstan/phpstan, friendsofphp/php-cs-fixer
- Presume that project dependencies are installed
- The PRADO Framework is at 'vendor/pradosoft/prado/'; its code is at 'vendor/pradosoft/prado/framework/'
- When information about the PRADO Framework is required, do a file search on its code.

## Directory Structure
```
./
├── agents/                     # The Coding Agents directory
│   ├── TODO.md                 # Open items for the project
│   └── working/                # Working Memory (see INDEX.md); legacy-generated-src/ holds superseded code
├── src/                        # The extension source; namespace Belisoful\Forum
│   ├── BEForumModule.php       # Bootstrap TDbPluginModule: configuration, permissions, managers, user behavior
│   ├── errorMessages.txt       # Error message definitions (forum_* keys)
│   ├── Behaviors/              # BEForumUserBehavior (attached to IUser)
│   ├── Content/                # Content renderers (Markdown, plain text) and mention parsing
│   ├── Data/                   # BEForumSchema (DDL/install) and BEForumRecord Active Records
│   ├── Exceptions/             # BEForum*Exception classes
│   ├── Feeds/                  # IFeedContentProvider and TJsonResponse providers
│   ├── Managers/               # Domain managers: threads, posts, members, reactions, polls, tags, subscriptions, notifications, search, moderation, attachments, statistics
│   ├── Pages/Forum/            # Template-only pages (.page) composed from template controls
│   ├── Security/               # Permission names and authorization rules
│   ├── Shell/                  # prado-cli "forum" shell action
│   ├── Util/                   # Slug, time, pagination helpers
│   └── Web/UI/                 # BEForumControl base class and all template controls (.php + .tpl), assets/
├── tests/                      # Test files
│   ├── app/                    # Minimal PRADO application used by the unit tests
│   ├── initdb_*.sql            # Database initialization files for CI
│   ├── test_tools/             # phpunit and phpstan bootstraps
│   └── unit/                   # phpunit tests, mirroring src/
├── composer.json               # Package configuration
├── README.md                   # Documentation
└── vendor/                     # the container for composer dependencies
```

## Cursor/Copilot Instructions
No specific Cursor or Copilot rules currently defined for this project.

# PRADO Framework Agent Safeguards -- ANTI-PATTERNS
Between the next brackets, it is required without exception:
{
- NEVER (without exception) execute the following "git" commands without asking the developer for approval first: clone, checkout, mv, restore, rm, branch, add, commit, merge, rebase, reset, pull, push, fetch
- NEVER (without exception) execute "rm" commands on any paths without asking the developer for approval first
- NEVER remove composer --dev dependencies because those are a required for development on the Project
- NEVER perform an action that erases or overwrites files for the task of unit testing and fixing; file changes are important and must be kept, because the changes themselves are being unit tested.
- NEVER delete any folders or files until the associated task is absolutely and totally complete.
}

## Working Memory Directory Information
- The Goal of Working Memory is to make Coding Agents more efficient at their project tasks.
- The Working Memory directory for planning, analysis, and knowledge is "agents/working/"
- The Working Memory directory may be used as a larger memory space to store and retain knowledge of the PRADO Framework for later use and analysis
- Scan (by file name) the Working Memory directory (recursively) for useful or relevant files to analyze relating to the task at hand
- Working Memory file names must reflect the contents for other agents to identify its usefulness
- Update the Working Memory files as needed for efficient coding and analysis.
- Clean up any Working Memory (and its directories) that are not needed or which are superseded
- When Working Memory involves only a specific PRADO class, create any directories needed to mimic the <relative_paths>/<class>.md file location heuristic
- The primary point into the Working Memory is agents/working/INDEX.md and recursive directory listing for keywords
- agents/working/INDEX.md should summarize the most important general Working Memory and knowledge links.

# Search URL References
- To Search the PHP language and libraries, use url: "https://www.php.net/search.php#gsc.tab=0&gsc.sort=&gsc.q=<replace with query string>"
- To look up inherent PHP functions, use url: "https://www.php.net/manual/en/function.<replace with PHP function>.php"
