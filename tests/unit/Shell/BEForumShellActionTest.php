<?php

use Belisoful\Forum\Shell\BEForumShellAction;
use Prado\IO\TTextWriter;
use Prado\Shell\TShellWriter;

class BEForumShellActionTest extends BEForumTestCase
{
	private TTextWriter $out;

	private function action(bool $withModule = true): BEForumShellAction
	{
		$action = new BEForumShellAction();
		$this->out = new TTextWriter();
		$writer = new TShellWriter($this->out);
		$writer->setColorSupported(false);
		$action->setWriter($writer);
		if ($withModule) {
			$action->setForumModule($this->forum);
		}
		return $action;
	}

	private function shellOutput(): string
	{
		return $this->out->flush();
	}

	public function testStatusInstallUpgrade(): void
	{
		$action = $this->action();
		self::assertSame('forum', $action->getAction());
		self::assertSame('status', $action->isValidAction(['forum']));
		self::assertSame('install', $action->isValidAction(['forum/install']));
		self::assertNull($action->isValidAction(['forum/unknown']));
		self::assertTrue($action->actionStatus(['forum/status']));
		$status = $this->shellOutput();
		self::assertStringContainsString('Driver:          ' . $this->forum->getSchema()->getDriverName(), $status);
		self::assertStringContainsString('Schema version:  1 of 1', $status);
		self::assertStringContainsString('Members:', $status);
		self::assertTrue($action->actionInstall(['forum/install']));
		self::assertStringContainsString('already exist', $this->shellOutput());
		self::assertTrue($action->actionUpgrade(['forum/upgrade']));
		self::assertStringContainsString('current at version', $this->shellOutput());
		$this->forum->getSchema()->drop();
		self::assertTrue($action->actionInstall(['forum/install']));
		self::assertStringContainsString('created forum_threads', $this->shellOutput());
	}

	public function testSeedRecountRerenderMaintenanceDdl(): void
	{
		$action = $this->action();
		$this->loginAs('admin');
		self::assertTrue($action->actionSeed(['forum/seed']));
		self::assertStringContainsString('Created category "General"', $this->shellOutput());
		self::assertCount(1, $this->forum->getBoards()->getBoards(true));
		self::assertTrue($action->actionSeed(['forum/seed', 'Other', 'Board']));
		self::assertStringContainsString('already has categories', $this->shellOutput());
		$board = $this->forum->getBoards()->getBoards(true)[0];
		$this->createThreadAs($board, 'alice');
		self::assertTrue($action->actionRecount(['forum/recount']));
		$recount = $this->shellOutput();
		self::assertStringContainsString('threads:', $recount);
		self::assertStringContainsString('members:', $recount);
		self::assertTrue($action->actionRerender(['forum/rerender']));
		self::assertStringContainsString('Re-rendered 1 posts', $this->shellOutput());
		self::assertTrue($action->actionMaintenance(['forum/maintenance']));
		self::assertStringContainsString('bans_expired:', $this->shellOutput());
		self::assertTrue($action->actionDdl(['forum/ddl', 'mysql']));
		self::assertStringContainsString('ENGINE=InnoDB', $this->shellOutput());
		self::assertTrue($action->actionDdl(['forum/ddl']));
		self::assertStringContainsString('AUTOINCREMENT', $this->shellOutput());
		self::assertTrue($action->actionDdl(['forum/ddl', 'oracle']));
		self::assertStringContainsString('not supported', $this->shellOutput());
	}

	public function testWithoutModule(): void
	{
		$action = $this->action(false);
		self::assertInstanceOf(\Belisoful\Forum\BEForumModule::class, $action->getForumModule(), 'a module is found in the application');
		$action = new BEForumShellAction();
		$out = new TTextWriter();
		$writer = new TShellWriter($out);
		$writer->setColorSupported(false);
		$action->setWriter($writer);
		$this->getApp()->getModulesByType(\Belisoful\Forum\BEForumModule::class);
		self::assertTrue($action->actionDdl(['forum/ddl', 'pgsql']));
		self::assertStringContainsString('SERIAL PRIMARY KEY', $out->flush());
	}
}
