<?php

/**
 * TForumBreadcrumbs class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;

/**
 * TForumBreadcrumbs renders a breadcrumb trail for the current
 * forum page.
 *
 * Usage in a .page template:
 * ```xml
 * <com:TForumBreadcrumbs ModuleID="forum" />
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumBreadcrumbs extends TForumControl
{
    /** @var string the module ID of the TForumManager */

    /** @var array breadcrumb entries: [ ['label'=>'…','url'=>'…'], … ] */
    private $_crumbs = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);
        // Crumbs are set externally by the host page before rendering.
        // Default: just the forum home link.
        if (empty($this->_crumbs)) {
            $this->_crumbs = [
                ['label' => 'Forum', 'url' => $this->getForumManager()->createUrl('forum/ForumHome')],
            ];
        }
    }

    // ===================================================================
    // Properties
    // ===================================================================


    /** @return array breadcrumb entries */
    public function getCrumbs(): array { return $this->_crumbs; }

    /**
     * Set the full breadcrumb trail.
     *
     * @param array $crumbs array of ['label' => '…', 'url' => '…'] (last entry has no url)
     */
    public function setCrumbs(array $crumbs): void { $this->_crumbs = $crumbs; }

    /**
     * Append a single breadcrumb entry.
     *
     * @param string      $label display text
     * @param string|null $url   null for the current (last) crumb
     */
    public function addCrumb(string $label, ?string $url = null): void
    {
        $this->_crumbs[] = ['label' => $label, 'url' => $url];
    }

}
