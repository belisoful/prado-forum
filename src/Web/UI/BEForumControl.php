<?php

/**
 * BEForumControl class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Exceptions\BEForumForbiddenException;
use Belisoful\Forum\Exceptions\BEForumNotFoundException;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Web\BEForumUrlBuilder;
use Prado\Exceptions\TExitException;
use Prado\Security\TAuthManager;
use Prado\TPropertyValue;
use Prado\Web\UI\TTemplateControl;
use Prado\Web\UI\WebControls\TLabel;

/**
 * BEForumControl class.
 *
 * BEForumControl is the base class of every forum template control.  A forum
 * control is a {@see \Prado\Web\UI\TTemplateControl} whose `.tpl` template
 * only renders a view model prepared in PHP: the control loads records through
 * the module managers in {@see onPreRender}, turns them into arrays of HTML
 * escaped values (see {@see e}) and binds them to repeaters whose rows are
 * rendered by {@see BEForumItemRenderer} subclasses.  Templates never contain
 * business logic.
 *
 * Controls locate their {@see \Belisoful\Forum\BEForumModule} through
 * {@see \Prado\Web\UI\TControl::getPluginModule} (or the `ModuleID` property
 * when several forums exist) and share the helpers of
 * {@see BEForumControlTrait}.  Any control may be placed on a host page:
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumThreadList BoardID="3" PageSize="10" />
 * ```
 *
 * Error display convention: a template may contain
 * `<com:TLabel ID="Error" Visible="false" />`; {@see showError} fills and
 * shows it, and {@see attempt} runs an action catching forum exceptions into
 * it.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
abstract class BEForumControl extends TTemplateControl
{
	use BEForumControlTrait;

	/** The client script key of the bundled stylesheet */
	public const STYLESHEET_KEY = 'beforum-styles';

	/** @var null|string the last error message */
	private ?string $_error = null;

	/**
	 * @return string additional CSS classes for the control wrapper
	 */
	public function getCssClass(): string
	{
		return (string) $this->getViewState('CssClass', '');
	}

	/**
	 * @param string $class additional CSS classes for the control wrapper
	 */
	public function setCssClass($class): void
	{
		$this->setViewState('CssClass', TPropertyValue::ensureString($class), '');
	}

	/**
	 * @param string $name the wrapper element name
	 * @return string the wrapper classes including {@see getCssClass}
	 */
	public function wrapperCss(string $name): string
	{
		return trim($this->css($name) . ' ' . $this->getCssClass());
	}

	/**
	 * @param string $name a request parameter
	 * @param int $default the default value
	 * @return int the parameter as integer
	 */
	public function getRequestInt(string $name, int $default = 0): int
	{
		$value = $this->getRequest()->itemAt($name);
		return ($value === null || $value === '' || !is_numeric($value)) ? $default : (int) $value;
	}

	/**
	 * @param string $name a request parameter
	 * @param string $default the default value
	 * @return string the parameter as string
	 */
	public function getRequestString(string $name, string $default = ''): string
	{
		$value = $this->getRequest()->itemAt($name);
		return $value === null ? $default : trim((string) $value);
	}

	/**
	 * @return int the requested 1-based page from the `page` parameter
	 */
	public function getRequestedPage(): int
	{
		return max(1, $this->getRequestInt(BEForumUrlBuilder::PARAM_PAGE, 1));
	}

	/**
	 * Sets the page title as `<title> - <forum title>`.
	 * @param null|string $title the title, null for the forum title alone
	 */
	public function setPageTitle(?string $title): void
	{
		$page = $this->getPage();
		if ($page === null) {
			return;
		}
		$forumTitle = $this->getForum()->getTitle();
		$page->setTitle($title === null || $title === '' ? $forumTitle : $title . ' - ' . $forumTitle);
	}

	/**
	 * Redirects the browser and ends the request.
	 * @param string $url the URL
	 */
	public function redirect(string $url): void
	{
		$this->getResponse()->redirect($url);
	}


	/**
	 * Ends the request after output has been written (file downloads).
	 * @throws TExitException always
	 */
	public function endRequest(): void
	{
		throw new TExitException();
	}

	/**
	 * Ensures the current user is authenticated: guests are redirected to the
	 * login page of the application's {@see TAuthManager} (with the current
	 * URL as return URL) or refused when no authentication manager exists.
	 * @param string $permission the permission reported when refused
	 * @throws BEForumForbiddenException when the user is a guest and no login page exists
	 */
	public function requireLogin(string $permission = BEForumPermissions::VIEW): void
	{
		if (!$this->getIsGuest()) {
			return;
		}
		foreach ($this->getApplication()->getModulesByType(TAuthManager::class) as $auth) {
			if ($auth instanceof TAuthManager && $auth->getLoginPage()) {
				$auth->setReturnUrl($this->getRequest()->getRequestUri());
				$this->redirect($this->getService()->constructUrl($auth->getLoginPage()));
				return;
			}
		}
		throw new BEForumForbiddenException($permission, 'forum_login_required');
	}

	/**
	 * @return null|string the last error message shown by {@see showError}
	 */
	public function getErrorMessage(): ?string
	{
		return $this->_error;
	}

	/**
	 * Shows an error message in the `Error` label of the template when present.
	 * @param string $message the message (plain text)
	 */
	public function showError(string $message): void
	{
		$this->_error = $message;
		$label = $this->findControl('Error');
		if ($label instanceof TLabel) {
			$label->setText($this->e($message));
			$label->setVisible(true);
		}
	}

	/**
	 * Runs an action, turning forum exceptions into a displayed error.
	 * @param callable $action the action
	 * @return bool whether the action completed without a forum exception
	 */
	protected function attempt(callable $action): bool
	{
		try {
			$action();
			return true;
		} catch (BEForumValidationException | BEForumForbiddenException | BEForumNotFoundException $e) {
			$this->showError($e->getMessage());
			return false;
		}
	}

	/**
	 * Registers the bundled stylesheet once per page when enabled.
	 */
	protected function registerStyles(): void
	{
		$page = $this->getPage();
		if ($page === null || !$this->getForum()->getEnableDefaultStyles()) {
			return;
		}
		$scripts = $page->getClientScript();
		if (!$scripts->isStyleSheetFileRegistered(self::STYLESHEET_KEY)) {
			$url = $this->publishAsset('assets/beforum.css', self::class);
			$scripts->registerStyleSheetFile(self::STYLESHEET_KEY, $url);
		}
	}

	/**
	 * Registers the stylesheet and lets subclasses bind their view model.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->registerStyles();
	}

	/**
	 * Binds a repeater found by id to a data source.
	 * @param string $id the repeater id
	 * @param array $data the rows
	 */
	protected function bindRepeater(string $id, array $data): void
	{
		$repeater = $this->findControl($id);
		if ($repeater !== null) {
			$repeater->setDataSource($data);
			$repeater->dataBind();
		}
	}

	/**
	 * Sets the visibility of a child control when it exists.
	 * @param string $id the control id
	 * @param bool $visible whether the control is visible
	 */
	protected function setChildVisible(string $id, bool $visible): void
	{
		$control = $this->findControl($id);
		if ($control !== null) {
			$control->setVisible($visible);
		}
	}
}
