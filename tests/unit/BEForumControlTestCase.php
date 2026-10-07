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
use Prado\Web\THttpResponse;
use Prado\Exceptions\TExitException;
use Prado\IO\IDataRenderer;
use Prado\Web\UI\TCommandEventParameter;
use Prado\Web\UI\ActiveControls\TCallbackResponseAdapter;
use Prado\Web\UI\TControl;
use Prado\Web\UI\THtmlWriter;
use Prado\Web\UI\WebControls\TRepeater;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;
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
		// redirects inspect the server software; the CLI has none
		$_SERVER['SERVER_SOFTWARE'] ??= 'PHPUnit';
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
	 * Renders a control through the page lifecycle and then invokes a postback
	 * handler on it, the way PRADO would after restoring the control tree.
	 * Redirects (`TExitException`) end the handler and are returned.
	 * @param BEForumControl $control the control
	 * @param callable $action `function(BEForumControl $control): void` invoking the handler
	 * @param array<string, mixed> $params the request parameters
	 * @return null|TExitException the redirect, null when the handler returned normally
	 */
	protected function postback(BEForumControl $control, callable $action, array $params = []): ?TExitException
	{
		$this->render($control, $params);
		return $this->invoke($control, $action);
	}

	/**
	 * Invokes a handler on an already rendered control.
	 * @param BEForumControl $control the control
	 * @param callable $action `function(BEForumControl $control): void` invoking the handler
	 * @return null|TExitException the redirect, null when the handler returned normally
	 */
	protected function invoke(TControl $control, callable $action): ?TExitException
	{
		// PRADO validates the page before raising a postback event
		$page = $control->getPage();
		if ($page !== null) {
			$page->validate();
		}
		try {
			$action($control);
		} catch (TExitException $e) {
			return $e;
		}
		return null;
	}

	/**
	 * Runs a real callback request: the control is rendered once (the GET
	 * request, which yields the page state), then a fresh control tree is run
	 * through the callback lifecycle targeting an active control, as the
	 * browser would.  The response adapter and error handler installed by the
	 * callback are removed afterwards.
	 * @param callable $factory `function(): BEForumControl` creating the control (called twice)
	 * @param callable $target `function(BEForumControl $control): TControl` returning the active control to trigger
	 * @param array<string, mixed> $params the request parameters
	 * @param mixed $parameter the callback parameter
	 * @param null|callable $between `function(): void` run between the GET render and the callback (state changes by others)
	 * @return array{control: BEForumControl, page: TPage, actions: array, content: string, redirect: null|string, html: string} the callback result
	 */
	protected function runCallback(callable $factory, callable $target, array $params = [], $parameter = '', ?callable $between = null): array
	{
		$first = $factory();
		$html = $this->render($first, $params);
		self::assertSame(1, preg_match('/name="' . TPage::FIELD_PAGESTATE . '"[^>]*value="([^"]*)"/', $html, $match), 'the GET response carries the page state');
		$uniqueId = $target($first)->getUniqueID();
		if ($between !== null) {
			$between();
		}
		$app = $this->getApp();
		$response = $app->getResponse();
		$errorHandler = $app->getErrorHandler();
		$this->setRequest($params + [
			TPage::FIELD_PAGESTATE => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			TPage::FIELD_CALLBACK_TARGET => $uniqueId,
			TPage::FIELD_CALLBACK_PARAMETER => json_encode($parameter),
		]);
		$control = $factory();
		$page = $this->createPage($control);
		$content = '';
		$redirect = null;
		try {
			$page->run(new THtmlWriter(new TTextWriter()));
		} finally {
			$adapter = $response->getAdapter();
			if ($adapter instanceof TCallbackResponseAdapter) {
				$redirect = $adapter->getRedirectedUrl();
				$writers = new ReflectionProperty(TCallbackResponseAdapter::class, '_writers');
				foreach ($writers->getValue($adapter) as $writer) {
					$content .= $writer->flush();
				}
			}
			// the callback adapter must not leak into later tests (it swallows redirects)
			$adapterProperty = new ReflectionProperty(THttpResponse::class, '_adapter');
			$adapterProperty->setValue($response, null);
			$app->setErrorHandler($errorHandler);
		}
		return [
			'control' => $control,
			'page' => $page,
			'actions' => $page->getCallbackClient()->getClientFunctionsToExecute(),
			'content' => $content,
			'redirect' => $redirect,
			'html' => $html,
		];
	}

	/**
	 * Builds a repeater command event parameter.
	 * @param string $name the command name
	 * @param mixed $parameter the command parameter
	 * @param null|TControl $item the repeater item
	 * @return TRepeaterCommandEventParameter the parameter
	 */
	protected function command(string $name, $parameter = null, ?TControl $item = null): TRepeaterCommandEventParameter
	{
		return new TRepeaterCommandEventParameter($item, null, new TCommandEventParameter($name, $parameter));
	}

	/**
	 * Finds the repeater item whose data row has a value.
	 * @param TRepeater $repeater the repeater
	 * @param string $key the row key
	 * @param mixed $value the value
	 * @return TControl the item
	 */
	protected function itemWhere(TRepeater $repeater, string $key, $value): TControl
	{
		foreach ($repeater->getItems() as $item) {
			$data = $item instanceof IDataRenderer || method_exists($item, 'getData') ? $item->getData() : null;
			if (is_array($data) && ($data[$key] ?? null) === $value) {
				return $item;
			}
		}
		self::fail('no repeater item with ' . $key . ' = ' . var_export($value, true));
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
