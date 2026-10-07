<?php

/**
 * BEForumControlTestCase class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

use Belisoful\Forum\Web\UI\BEForumControl;
use Prado\IO\TTextWriter;
use Prado\Prado;
use Prado\Web\Services\TPageService;
use Prado\Web\TAssetManager;
use Prado\Web\UI\THtmlWriter;
use Prado\Web\UI\TForm;
use Prado\Web\UI\TPage;
use Prado\Web\UI\WebControls\THead;

/**
 * BEForumControlTestCase class.
 *
 * BEForumControlTestCase renders forum template controls through a real
 * {@see \Prado\Web\UI\TPage} lifecycle (init, load, pre-render, render) with a
 * page service, an asset manager and request parameters, so templates,
 * bindings and view models are exercised end to end.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
abstract class BEForumControlTestCase extends BEForumTestCase
{
	/**
	 * Installs the page service and asset manager.
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$app = $this->getApp();
		$assets = dirname(__DIR__) . '/app/runtime/assets';
		if (!is_dir($assets)) {
			@mkdir($assets, 0o775, true);
		}
		if (Prado::getPathOfAlias('BEForumTestAssets') === null) {
			Prado::setPathOfAlias('BEForumTestAssets', $assets);
		}
		$manager = new TAssetManager();
		$manager->setBasePath('BEForumTestAssets');
		$manager->setBaseUrl('/assets');
		$manager->init(null);
		$app->setAssetManager($manager);
		$service = new TPageService();
		$service->setID('page');
		$app->setService($service);
		$service->init(null);
	}

	/**
	 * Clears request parameters.
	 */
	protected function tearDown(): void
	{
		$this->getApp()->getRequest()->clear();
		parent::tearDown();
	}

	/**
	 * Sets the request parameters.
	 * @param array<string, mixed> $params the parameters
	 */
	protected function setRequest(array $params): void
	{
		$request = $this->getApp()->getRequest();
		$request->clear();
		foreach ($params as $name => $value) {
			$request->add($name, $value);
		}
	}

	/**
	 * Creates a page with a head and a form holding the control.
	 * @param BEForumControl $control the control
	 * @return TPage the page
	 */
	protected function createPage(BEForumControl $control): TPage
	{
		$page = new TPage();
		$page->setPagePath('Forum.Test');
		$page->getControls()->add(new THead());
		$form = new TForm();
		$form->setID('form');
		$page->getControls()->add($form);
		$control->setForum($this->forum);
		$form->getControls()->add($control);
		return $page;
	}

	/**
	 * Renders a control through the page lifecycle.
	 * @param BEForumControl $control the control
	 * @param array<string, mixed> $params the request parameters
	 * @param null|callable $configure `function(BEForumControl $control): void` run before the lifecycle
	 * @return string the HTML
	 */
	protected function render(BEForumControl $control, array $params = [], ?callable $configure = null): string
	{
		$this->setRequest($params);
		$page = $this->createPage($control);
		if ($configure !== null) {
			$configure($control);
		}
		$textWriter = new TTextWriter();
		$page->run(new THtmlWriter($textWriter));
		return $textWriter->flush();
	}

	/**
	 * Renders a control of a class.
	 * @param string $class the control class
	 * @param array<string, mixed> $params the request parameters
	 * @param null|callable $configure `function(BEForumControl $control): void` run before the lifecycle
	 * @return string the HTML
	 */
	protected function renderClass(string $class, array $params = [], ?callable $configure = null): string
	{
		return $this->render(new $class(), $params, $configure);
	}
}
