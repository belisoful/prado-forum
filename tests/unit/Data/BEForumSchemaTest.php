<?php

use Belisoful\Forum\Data\BEForumSchema;
use Belisoful\Forum\Exceptions\BEForumConfigurationException;
use PHPUnit\Framework\TestCase;
use Prado\Data\TDbConnection;
use Prado\Exceptions\TInvalidDataValueException;

class BEForumSchemaTest extends TestCase
{
	private string $file = '';

	protected function tearDown(): void
	{
		if ($this->file !== '' && is_file($this->file)) {
			@unlink($this->file);
		}
		parent::tearDown();
	}

	private function connection(): TDbConnection
	{
		$this->file = tempnam(sys_get_temp_dir(), 'beforum-schema-');
		$db = new TDbConnection('sqlite:' . $this->file);
		$db->setActive(true);
		return $db;
	}

	public function testInstallUpgradeAndDrop(): void
	{
		$schema = new BEForumSchema($this->connection(), 'x_');
		self::assertSame('x_', $schema->getTablePrefix());
		self::assertSame('x_threads', $schema->getTableName('threads'));
		self::assertFalse($schema->getIsInstalled());
		self::assertSame(0, $schema->getInstalledVersion());
		self::assertSame([], $schema->findExistingTables());
		$created = $schema->install();
		self::assertCount(count($schema->getTableNames()), $created);
		self::assertContains('x_posts', $created);
		self::assertTrue($schema->getIsInstalled());
		self::assertSame(BEForumSchema::VERSION, $schema->getInstalledVersion());
		self::assertFalse($schema->getNeedsUpgrade());
		self::assertTrue($schema->tableExists('meta'));
		self::assertNotNull($schema->getMeta(BEForumSchema::META_INSTALLED_AT));
		self::assertSame([], $schema->install(), 'a second install creates nothing');
		self::assertSame(BEForumSchema::VERSION, $schema->upgrade());
		$schema->setMeta('custom', 'value');
		self::assertSame('value', $schema->getMeta('custom'));
		$schema->setMeta('custom', 'other');
		self::assertSame('other', $schema->getMeta('custom'));
		$schema->setMeta('custom', 'other');
		self::assertSame('other', $schema->getMeta('custom'), 'an unchanged value is written without a duplicate key');
		$schema->setMeta('custom', null);
		self::assertNull($schema->getMeta('custom'));
		self::assertSame('fallback', $schema->getMeta('missing', 'fallback'));
		$schema->drop();
		self::assertSame([], $schema->findExistingTables());
		self::assertFalse($schema->getIsInstalled());
	}

	public function testClearEmptiesTablesAndKeepsTheSchema(): void
	{
		$connection = $this->connection();
		$schema = new BEForumSchema($connection, 'c_');
		$schema->install();
		$connection->createCommand("INSERT INTO c_categories (name, slug, position, board_count, created_at, updated_at) VALUES ('A', 'a', 1, 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00')")->execute();
		self::assertSame(1, (int) $connection->createCommand('SELECT COUNT(*) FROM c_categories')->queryScalar());
		self::assertSame(count($schema->getTableNames()) - 1, $schema->clear());
		self::assertSame(0, (int) $connection->createCommand('SELECT COUNT(*) FROM c_categories')->queryScalar());
		self::assertTrue($schema->getIsInstalled(), 'meta survives a clear');
		$connection->createCommand("INSERT INTO c_categories (name, slug, position, board_count, created_at, updated_at) VALUES ('B', 'b', 1, 0, '2026-01-01 00:00:00', '2026-01-01 00:00:00')")->execute();
		self::assertSame(1, (int) $connection->createCommand('SELECT id FROM c_categories')->queryScalar(), 'SQLite ids restart after a clear');
		$schema->drop();
		self::assertSame(0, $schema->clear(), 'nothing to clear without tables');
	}

	public function testUpgradeFromNothingInstalls(): void
	{
		$schema = new BEForumSchema($this->connection());
		self::assertSame(BEForumSchema::VERSION, $schema->upgrade());
		self::assertTrue($schema->getIsInstalled());
		$schema->setMeta(BEForumSchema::META_SCHEMA_VERSION, '0');
		self::assertSame(0, $schema->getInstalledVersion());
		self::assertSame(BEForumSchema::VERSION, $schema->upgrade());
	}

	public function testDdlForEveryDriver(): void
	{
		$schema = new BEForumSchema(null, 'forum_');
		foreach (['sqlite', 'mysql', 'pgsql'] as $driver) {
			$statements = $schema->getCreateStatements($driver);
			self::assertGreaterThan(count($schema->getTableNames()), count($statements));
			self::assertStringStartsWith('CREATE TABLE', $statements[0]);
			$drops = $schema->getDropStatements($driver);
			self::assertCount(count($schema->getTableNames()), $drops);
			self::assertStringStartsWith('DROP TABLE IF EXISTS', $drops[0]);
		}
		$mysql = implode("\n", $schema->getCreateStatements('mysql'));
		self::assertStringContainsString('ENGINE=InnoDB', $mysql);
		self::assertStringContainsString('`forum_threads`', $mysql);
		self::assertStringContainsString('TINYINT(1)', $mysql);
		self::assertStringContainsString('AUTO_INCREMENT', $mysql);
		$pgsql = implode("\n", $schema->getCreateStatements('pgsql'));
		self::assertStringContainsString('SERIAL PRIMARY KEY', $pgsql);
		self::assertStringContainsString('BOOLEAN', $pgsql);
		self::assertStringContainsString("DEFAULT 'discussion'", $pgsql);
		self::assertStringContainsString('DEFAULT FALSE', $pgsql);
		$sqlite = implode("\n", $schema->getCreateStatements('sqlite'));
		self::assertStringContainsString('INTEGER PRIMARY KEY AUTOINCREMENT', $sqlite);
		self::assertStringContainsString('REFERENCES "forum_boards" ("id") ON DELETE CASCADE', $sqlite);
		self::assertStringContainsString('CREATE UNIQUE INDEX "ix_forum_members_username"', $sqlite);
	}

	public function testNormalizeDriver(): void
	{
		self::assertSame('sqlite', BEForumSchema::normalizeDriver('SQLite3'));
		self::assertSame('mysql', BEForumSchema::normalizeDriver('mariadb'));
		self::assertSame('pgsql', BEForumSchema::normalizeDriver('postgres'));
		$this->expectException(BEForumConfigurationException::class);
		BEForumSchema::normalizeDriver('oracle');
	}

	public function testColumnHelpers(): void
	{
		self::assertSame('VARCHAR(50)', BEForumSchema::getColumnType(['string', 'length' => 50], 'sqlite'));
		self::assertSame('MEDIUMTEXT', BEForumSchema::getColumnType(['longtext'], 'mysql'));
		self::assertSame('TEXT', BEForumSchema::getColumnType(['longtext'], 'pgsql'));
		self::assertSame('DOUBLE PRECISION', BEForumSchema::getColumnType(['float'], 'pgsql'));
		self::assertSame('BIGINT', BEForumSchema::getColumnType(['bigint'], 'mysql'));
		self::assertSame('TIMESTAMP', BEForumSchema::getColumnType(['datetime'], 'pgsql'));
		self::assertNull(BEForumSchema::getColumnDefault(['int'], 'sqlite'));
		self::assertSame('NULL', BEForumSchema::getColumnDefault(['int', 'default' => null], 'sqlite'));
		self::assertSame('1', BEForumSchema::getColumnDefault(['bool', 'default' => true], 'mysql'));
		self::assertSame('TRUE', BEForumSchema::getColumnDefault(['bool', 'default' => true], 'pgsql'));
		self::assertSame("'it''s'", BEForumSchema::getColumnDefault(['string', 'default' => "it's"], 'sqlite'));
		self::assertSame('"name" VARCHAR(255) NULL', BEForumSchema::getColumnDefinition('name', ['string', 'null' => true], 'sqlite'));
		self::assertSame('`n` INT NOT NULL DEFAULT 0', BEForumSchema::getColumnDefinition('n', ['int', 'default' => 0], 'mysql'));
		$this->expectException(TInvalidDataValueException::class);
		BEForumSchema::getColumnType(['blob'], 'sqlite');
	}

	public function testErrors(): void
	{
		$schema = new BEForumSchema();
		try {
			$schema->install();
			self::fail('install without connection must throw');
		} catch (BEForumConfigurationException $e) {
			self::assertStringContainsString('connection', $e->getMessage());
		}
		try {
			$schema->getCreateStatementsFor('nope', 'sqlite');
			self::fail('unknown table must throw');
		} catch (TInvalidDataValueException $e) {
			self::assertStringContainsString('nope', $e->getMessage());
		}
		$this->expectException(TInvalidDataValueException::class);
		$schema->setTablePrefix('bad-prefix');
	}
}
