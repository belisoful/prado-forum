<?php

/**
 * PHPUnit bootstrap: loads the autoloader and creates the test application.
 *
 * The application is created once for the whole test run from `tests/app`
 * (no request is run).  Tests that need a module instantiate it themselves,
 * see `tests/unit/BEForumTestCase.php`.
 */
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');

// PRADO 4.3.2 reads a NULL column default in TPgsqlMetaData with substr(), which PHP 8.1+ reports
// as a deprecation that PRADO's error handler turns into an exception; fixed in PRADO master.
if (str_starts_with((string) getenv('BEFORUM_TEST_DSN'), 'pgsql:')) {
	error_reporting(E_ALL & ~E_DEPRECATED);
}

require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../unit/BEForumTestCase.php');
require_once(__DIR__ . '/../unit/BEForumControlTestCase.php');

if (\Prado\Prado::getApplication() === null) {
	$app = new \Prado\TApplication(__DIR__ . '/../app', false);
	$app->setMode(\Prado\TApplicationMode::Debug);
}
