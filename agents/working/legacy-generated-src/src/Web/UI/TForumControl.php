<?php

/**
 * TForumControl base class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Web\UI;

use Prado\Web\UI\TTemplateControl;
use Belisoful\Forum\Exceptions\TForumConfigurationException;
use Belisoful\Forum\TForumManager;

/**
 * TForumControl is the base class for all forum template controls (portlets).
 *
 * It provides the common {@link getModuleID}/{@link setModuleID} property and
 * the guarded {@link getForumManager()} helper that every forum portlet needs.
 *
 * Subclasses set their own `@TemplateControl` annotation pointing to their
 * paired `.tpl` file in the same directory.
 *
 * ### Usage
 *
 * ```xml
 * <!-- application.xml / module config -->
 * <module id="forum" class="Belisoful\Forum\TForumManager" ConnectionID="db" />
 *
 * <!-- page template -->
 * <com:TForumCategories ModuleID="forum" />
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
abstract class TForumControl extends TTemplateControl
{
    /**
     * @var string module ID of the {@link TForumManager} instance.
     */
    private string $_moduleId = 'forum';

    // ===================================================================
    // Module resolution
    // ===================================================================

    /**
     * Returns the {@link TForumManager} module identified by {@link getModuleID}.
     *
     * @throws TForumConfigurationException if the module is not found or is not a TForumManager.
     */
    protected function getForumManager(): TForumManager
    {
        $module = $this->getApplication()->getModule($this->_moduleId);
        if (!($module instanceof TForumManager)) {
            throw new TForumConfigurationException(
                'forum_control_manager_not_found',
                get_class($this),
                $this->_moduleId
            );
        }
        return $module;
    }

    // ===================================================================
    // Properties
    // ===================================================================

    /**
     * @return string module ID of the TForumManager instance. Defaults to 'forum'.
     */
    public function getModuleID(): string
    {
        return $this->_moduleId;
    }

    /**
     * @param string $value module ID of the TForumManager instance.
     */
    public function setModuleID(string $value): void
    {
        $this->_moduleId = $value;
    }
}
