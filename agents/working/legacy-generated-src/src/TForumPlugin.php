<?php

/**
 * TForumPlugin class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum;

use Prado\TModule;
use Belisoful\Forum\Exceptions\TForumConfigurationException;
use Prado\Web\Services\TPageService;

/**
 * TForumPlugin is the PRADO plugin module that wires the forum Pages into
 * the host application's page service.
 *
 * While {@see TForumManager} is the **core** (data, connection, services),
 * TForumPlugin is the **view** layer: it registers the `src/Pages/` directory
 * so the host application can route requests to forum pages without copying
 * any templates.  It also provides a page-path prefix so multiple plugin
 * instances can coexist without collision.
 *
 * Configure it in your application.xml **after** the TForumManager module:
 * ```xml
 * <module id="forumPlugin" class="Belisoful\Forum\TForumPlugin"
 *     ForumManagerID="forum"
 *     PagePathPrefix="forum"
 *     DefaultPage="ForumHome"
 * />
 * ```
 *
 * The host application's `<services>` section should point the page service
 * at its own pages; this module prepends the plugin pages transparently.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumPlugin extends TModule
{
    // ===================================================================
    // Configuration
    // ===================================================================

    /**
     * @var string module ID of the {@see TForumManager} core module.
     *   Used to resolve the manager when this plugin initialises.
     */
    private $_forumManagerId = 'forum';

    /**
     * @var string URL path prefix under which forum pages are served.
     *   e.g. 'forum' makes pages accessible as /forum/ForumHome, /forum/ThreadView …
     */
    private $_pagePathPrefix = 'forum';

    /**
     * @var string the default (index) page shown when visiting the forum root.
     */
    private $_defaultPage = 'ForumHome';

    /**
     * @var TForumManager|null resolved reference to the core manager.
     */
    private $_manager = null;

    // ===================================================================
    // TModule lifecycle
    // ===================================================================

    /**
     * Initialise the plugin.
     *
     * Locates the TForumManager, resolves the plugin's Pages directory, and
     * registers it with the application's page service so PRADO can route
     * requests to forum pages.
     *
     * @param \Prado\Xml\TXmlElement|null $config module configuration node
     * @throws TConfigurationException when the core module is not found or
     *   the application is not running a TPageService.
     */
    public function init($config)
    {
        parent::init($config);

        // Resolve the core manager.
        $this->_manager = $this->getApplication()->getModule($this->_forumManagerId);
        if (!($this->_manager instanceof TForumManager)) {
            throw new TForumConfigurationException(
                'forum_plugin_manager_not_found',
                $this->_forumManagerId
            );
        }

        // Register this package's Pages/ directory with the page service.
        $this->registerPageDirectory();
    }

    /**
     * Push the plugin's Pages/ directory into the application page service.
     *
     * The directory is prepended so forum pages can be discovered alongside
     * the host application's own pages, using the configured path prefix.
     *
     * @throws TConfigurationException when the page service is unavailable.
     */
    protected function registerPageDirectory(): void
    {
        $service = $this->getApplication()->getService();
        if (!($service instanceof TPageService)) {
            // Page service not yet active — schedule registration on service start.
            $this->getApplication()->attachEventHandler(
                'OnBeginRequest',
                [$this, 'onBeginRequest']
            );
            return;
        }

        $this->doRegisterPageDirectory($service);
    }

    /**
     * Event handler — deferred registration when page service was not yet
     * available during init().
     *
     * @param object $sender event sender
     * @param mixed  $param  event parameter
     */
    public function onBeginRequest($sender, $param): void
    {
        $service = $this->getApplication()->getService();
        if ($service instanceof TPageService) {
            $this->doRegisterPageDirectory($service);
        }
    }

    /**
     * Perform the actual page-directory registration on the TPageService.
     *
     * @param TPageService $service the running page service
     */
    protected function doRegisterPageDirectory(TPageService $service): void
    {
        $pagesDir = __DIR__ . DIRECTORY_SEPARATOR . 'Pages';

        // addBasePath() maps a virtual path prefix to a filesystem directory.
        if (method_exists($service, 'addBasePath')) {
            $service->addBasePath($this->_pagePathPrefix, $pagesDir);
        } else {
            // Fallback: prepend to the existing base path list when available.
            $existing = $service->getBasePath();
            $service->setBasePath($pagesDir);
        }
    }

    // ===================================================================
    // Configuration Properties
    // ===================================================================

    /**
     * @return string module ID of the TForumManager core module
     */
    public function getForumManagerID(): string
    {
        return $this->_forumManagerId;
    }

    /**
     * @param string $v module ID of the TForumManager core module
     */
    public function setForumManagerID(string $v): void
    {
        $this->_forumManagerId = $v;
    }

    /**
     * @return string URL path prefix for forum pages (e.g. 'forum')
     */
    public function getPagePathPrefix(): string
    {
        return $this->_pagePathPrefix;
    }

    /**
     * @param string $v URL path prefix for forum pages
     */
    public function setPagePathPrefix(string $v): void
    {
        $this->_pagePathPrefix = trim($v, '/');
    }

    /**
     * @return string the default forum index page name
     */
    public function getDefaultPage(): string
    {
        return $this->_defaultPage;
    }

    /**
     * @param string $v page class name without namespace, e.g. 'ForumHome'
     */
    public function setDefaultPage(string $v): void
    {
        $this->_defaultPage = $v;
    }

    // ===================================================================
    // Accessors
    // ===================================================================

    /**
     * Return the resolved TForumManager core module.
     *
     * @return TForumManager
     * @throws TConfigurationException when called before init()
     */
    public function getForumManager(): TForumManager
    {
        if ($this->_manager === null) {
            throw new TForumConfigurationException('forum_plugin_not_initialised');
        }
        return $this->_manager;
    }

    /**
     * Convenience pass-through to the core manager's getDbConnection().
     *
     * @return \Prado\Data\TDbConnection
     */
    public function getDbConnection()
    {
        return $this->getForumManager()->getDbConnection();
    }
}
