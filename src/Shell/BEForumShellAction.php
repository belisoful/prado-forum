<?php

/**
 * BEForumShellAction class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Shell;

use Belisoful\Forum\BEForumModule;
use Belisoful\Forum\Data\BEForumSchema;
use Prado\Prado;
use Prado\Shell\TShellAction;
use Prado\Shell\TShellWriter;

/**
 * BEForumShellAction class.
 *
 * BEForumShellAction adds the `forum` command to `prado-cli`:
 * ```
 * php prado-cli.php forum/status        # schema version, table count, statistics
 * php prado-cli.php forum/install       # creates the missing tables
 * php prado-cli.php forum/upgrade       # applies pending migrations
 * php prado-cli.php forum/seed          # creates a default category and board when the forum is empty
 * php prado-cli.php forum/recount       # recomputes every denormalised counter
 * php prado-cli.php forum/rerender      # re-renders the HTML of every post
 * php prado-cli.php forum/maintenance   # runs the cron maintenance once
 * php prado-cli.php forum/ddl [driver]  # prints the DDL for sqlite, mysql or pgsql
 * ```
 * The action is registered by {@see BEForumModule::registerShellAction} when
 * the current user holds the `forum_shell` permission (or no permissions
 * manager is installed).
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumShellAction extends TShellAction
{
	protected $action = 'forum';
	protected $methods = ['status', 'install', 'upgrade', 'seed', 'recount', 'rerender', 'maintenance', 'ddl'];
	protected $parameters = [null, null, null, null, null, null, null, null];
	protected $optional = [null, null, null, ['category-name', 'board-name'], null, null, null, ['driver']];
	protected $description = [
		'Installs, upgrades and maintains the PRADO Forum extension.',
		'Shows the schema version, the tables and the forum statistics.',
		'Creates the missing forum tables.',
		'Applies the pending schema migrations.',
		'Creates a default category and board when the forum has none.',
		'Recomputes every denormalised counter (boards, threads, members, tags, reactions).',
		'Re-renders the HTML of every post with the current content renderer.',
		'Runs the cron maintenance once (expired bans and pins, old notifications, unused tags).',
		'Prints the DDL statements for a driver: sqlite (default), mysql or pgsql.',
	];

	/** @var null|BEForumModule the module */
	private ?BEForumModule $_module = null;

	/**
	 * @return null|BEForumModule the module
	 */
	public function getForumModule(): ?BEForumModule
	{
		if ($this->_module === null) {
			$app = Prado::getApplication();
			if ($app !== null) {
				foreach ($app->getModulesByType(BEForumModule::class) as $module) {
					if ($module instanceof BEForumModule) {
						$this->_module = $module;
						break;
					}
				}
			}
		}
		return $this->_module;
	}

	/**
	 * @param BEForumModule $module the module
	 */
	public function setForumModule(BEForumModule $module): void
	{
		$this->_module = $module;
	}

	/**
	 * @return null|BEForumModule the module, writing an error when missing
	 */
	protected function requireModule(): ?BEForumModule
	{
		$module = $this->getForumModule();
		if ($module === null) {
			$this->getWriter()->writeError('No BEForumModule is configured in the application.');
		}
		return $module;
	}

	/**
	 * Shows the schema status and the statistics.
	 * @param array $args the arguments
	 * @return bool whether the action was handled
	 */
	public function actionStatus($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$writer = $this->getWriter();
		$schema = $module->getSchema();
		$version = $schema->getInstalledVersion();
		$writer->writeLine();
		$writer->writeLine('PRADO Forum ' . BEForumModule::VERSION, [TShellWriter::BLUE, TShellWriter::BOLD]);
		$writer->writeLine('Driver:          ' . $schema->getDriverName());
		$writer->writeLine('Table prefix:    ' . $module->getTablePrefix());
		$writer->writeLine('Schema version:  ' . ($version === 0 ? 'not installed' : $version . ' of ' . BEForumSchema::VERSION));
		$writer->writeLine('Tables present:  ' . count($schema->findExistingTables()) . ' of ' . count($schema->getTableNames()));
		if ($version > 0) {
			$summary = $module->getStatistics()->getSummary(true);
			$writer->writeLine('Threads:         ' . $summary['threads']);
			$writer->writeLine('Posts:           ' . $summary['posts']);
			$writer->writeLine('Members:         ' . $summary['members']);
		}
		$writer->writeLine();
		return true;
	}

	/**
	 * Creates the missing tables.
	 * @param array $args the arguments
	 * @return bool whether the action was handled
	 */
	public function actionInstall($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$writer = $this->getWriter();
		$created = $module->getSchema()->install();
		if ($created) {
			foreach ($created as $table) {
				$writer->writeLine('created ' . $table);
			}
		} else {
			$writer->writeLine('All forum tables already exist.');
		}
		$module->ensureSchema(true);
		$writer->writeLine('Schema version ' . $module->getSchema()->getInstalledVersion() . '.', TShellWriter::GREEN);
		return true;
	}

	/**
	 * Applies pending migrations.
	 * @param array $args the arguments
	 * @return bool whether the action was handled
	 */
	public function actionUpgrade($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$writer = $this->getWriter();
		$before = $module->getSchema()->getInstalledVersion();
		$after = $module->getSchema()->upgrade();
		$module->ensureSchema(true);
		if ($after === $before) {
			$writer->writeLine('Schema is current at version ' . $after . '.');
		} else {
			$writer->writeLine('Schema upgraded from version ' . $before . ' to ' . $after . '.', TShellWriter::GREEN);
		}
		return true;
	}

	/**
	 * Creates a default category and board when the forum is empty.
	 * @param array $args the arguments: optional category name and board name
	 * @return bool whether the action was handled
	 */
	public function actionSeed($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$writer = $this->getWriter();
		$module->ensureSchema(true);
		$boards = $module->getBoards();
		if ($boards->getCategories(true)) {
			$writer->writeLine('The forum already has categories; nothing seeded.');
			return true;
		}
		$categoryName = $args[1] ?? 'General';
		$boardName = $args[2] ?? 'General Discussion';
		$category = $boards->createCategory($categoryName);
		$board = $boards->createBoard($category, $boardName, 'Talk about anything.');
		$writer->writeLine('Created category "' . $category->name . '" (#' . $category->getId() . ') and board "' . $board->name . '" (#' . $board->getId() . ').', TShellWriter::GREEN);
		return true;
	}

	/**
	 * Recomputes every counter.
	 * @param array $args the arguments
	 * @return bool whether the action was handled
	 */
	public function actionRecount($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$writer = $this->getWriter();
		foreach ($module->recountStatistics() as $area => $count) {
			$writer->writeLine($this->getWriter()->pad($area . ':', 12) . $count);
		}
		return true;
	}

	/**
	 * Re-renders every post.
	 * @param array $args the arguments
	 * @return bool whether the action was handled
	 */
	public function actionRerender($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$module->ensureSchema(true);
		$this->getWriter()->writeLine('Re-rendered ' . $module->getPosts()->rerenderAll() . ' posts.', TShellWriter::GREEN);
		return true;
	}

	/**
	 * Runs the maintenance once.
	 * @param array $args the arguments
	 * @return bool whether the action was handled
	 */
	public function actionMaintenance($args): bool
	{
		if (($module = $this->requireModule()) === null) {
			return true;
		}
		$writer = $this->getWriter();
		foreach ($module->runMaintenance() as $area => $count) {
			$writer->writeLine($this->getWriter()->pad($area . ':', 24) . $count);
		}
		return true;
	}

	/**
	 * Prints the DDL for a driver.
	 * @param array $args the arguments: optional driver
	 * @return bool whether the action was handled
	 */
	public function actionDdl($args): bool
	{
		$module = $this->getForumModule();
		$schema = $module ? $module->getSchema() : new BEForumSchema();
		$driver = $args[1] ?? BEForumSchema::DRIVER_SQLITE;
		$writer = $this->getWriter();
		try {
			foreach ($schema->getCreateStatements($driver) as $statement) {
				$writer->writeLine($statement . ';');
			}
		} catch (\Throwable $e) {
			$writer->writeError($e->getMessage());
		}
		return true;
	}
}
