<?php

/**
 * BEForumSubscribeButton class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Data\BEForumSubscription;
use Belisoful\Forum\Security\BEForumPermissions;
use Prado\TPropertyValue;

/**
 * BEForumSubscribeButton class.
 *
 * BEForumSubscribeButton toggles the subscription of the current member to a
 * board or a thread.  It is hidden for guests and when subscriptions are
 * disabled.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumSubscribeButton TargetType="thread" TargetID="12" />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TLinkButton $Toggle
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSubscribeButton extends BEForumControl
{
	/**
	 * @return string the target type: board or thread
	 */
	public function getTargetType(): string
	{
		return (string) $this->getViewState('TargetType', BEForumSubscription::TYPE_THREAD);
	}

	/**
	 * @param string $type the target type: board or thread
	 */
	public function setTargetType($type): void
	{
		$this->setViewState('TargetType', strtolower(TPropertyValue::ensureString($type)), BEForumSubscription::TYPE_THREAD);
	}

	/**
	 * @return int the target id
	 */
	public function getTargetID(): int
	{
		return (int) $this->getViewState('TargetID', 0);
	}

	/**
	 * @param int $id the target id
	 */
	public function setTargetID($id): void
	{
		$this->setViewState('TargetID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return bool whether the current member is subscribed
	 */
	public function getIsSubscribed(): bool
	{
		return $this->getTargetID() > 0 && $this->getForum()->getSubscriptions()->isSubscribed($this->getTargetType(), $this->getTargetID());
	}

	/**
	 * @return bool whether the button applies to the current user
	 */
	public function getIsAvailable(): bool
	{
		return $this->getTargetID() > 0 && !$this->getIsGuest() && $this->getForum()->getEnableSubscriptions() && $this->can(BEForumPermissions::SUBSCRIBE);
	}

	/**
	 * Toggles the subscription.
	 * @param mixed $sender the button
	 * @param mixed $param the event parameter
	 */
	public function toggleClicked($sender, $param): void
	{
		$this->attempt(function (): void {
			$this->getForum()->getSubscriptions()->toggle($this->getTargetType(), $this->getTargetID());
		});
	}

	/**
	 * Updates the button state.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$available = $this->getIsAvailable();
		$this->setVisible($available);
		if ($available) {
			$subscribed = $this->getIsSubscribed();
			$this->Toggle->setText($this->e($subscribed ? $this->t('Unsubscribe') : $this->t('Subscribe')));
			$this->Toggle->setCssClass($this->css('button', $subscribed ? 'active' : 'secondary'));
			$this->Toggle->setToolTip($subscribed ? $this->t('Stop receiving notifications') : $this->t('Receive notifications about new activity'));
		}
	}
}
