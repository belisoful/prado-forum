<?php

/**
 * BEForumReactionBar class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Belisoful\Forum\Security\BEForumPermissions;
use Prado\TPropertyValue;
use Prado\Web\UI\WebControls\TRepeaterCommandEventParameter;

/**
 * BEForumReactionBar class.
 *
 * BEForumReactionBar shows the reaction counts of a post and lets the member
 * toggle a reaction.  The reaction types come from the module property
 * `ReactionTypes`; the symbols shown can be overridden with {@see setSymbols}.
 * The owning post view passes the pre-loaded summary through
 * {@see setSummary}/{@see setMemberReaction}; after a postback the bar loads
 * them itself.
 *
 * @property \Prado\Web\UI\ActiveControls\TActivePanel $Panel
 * @property \Prado\Web\UI\WebControls\TRepeater $Buttons
 * @property \Prado\Web\UI\WebControls\TRepeater $Counts
 * @property \Prado\Web\UI\WebControls\TLabel $Error
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumReactionBar extends BEForumControl
{
	/** @var bool whether this bar handled the current callback and must be re-rendered */
	private bool $_refresh = false;

	/** @var array<string, string> default symbols keyed by reaction type */
	public const DEFAULT_SYMBOLS = ['like' => '&#128077;', 'love' => '&#10084;&#65039;', 'laugh' => '&#128514;', 'wow' => '&#128558;', 'sad' => '&#128546;', 'angry' => '&#128545;', 'thanks' => '&#128591;'];

	/** @var null|array<string, int> the counts keyed by type */
	private ?array $_summary = null;

	/** @var null|string the type the current member reacted with, empty for none */
	private ?string $_memberReaction = null;

	/** @var array<string, string> symbols keyed by type */
	private array $_symbols = self::DEFAULT_SYMBOLS;

	/**
	 * @return int the post id
	 */
	public function getPostID(): int
	{
		return (int) $this->getViewState('PostID', 0);
	}

	/**
	 * @param int $id the post id
	 */
	public function setPostID($id): void
	{
		$this->setViewState('PostID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @return int the author member id (authors cannot react to their own posts)
	 */
	public function getAuthorID(): int
	{
		return (int) $this->getViewState('AuthorID', 0);
	}

	/**
	 * @param int $id the author member id
	 */
	public function setAuthorID($id): void
	{
		$this->setViewState('AuthorID', TPropertyValue::ensureInteger($id), 0);
	}

	/**
	 * @param null|array<string, int> $summary the counts keyed by type
	 */
	public function setSummary(?array $summary): void
	{
		$this->_summary = $summary;
	}

	/**
	 * @return array<string, int> the counts keyed by type
	 */
	public function getSummary(): array
	{
		if ($this->_summary === null) {
			$post = $this->getForum()->getPosts()->findPost($this->getPostID());
			$this->_summary = $post ? $this->getForum()->getReactions()->getSummary($post) : [];
		}
		return $this->_summary;
	}

	/**
	 * @param null|string $type the type the current member reacted with, empty string for none
	 */
	public function setMemberReaction(?string $type): void
	{
		$this->_memberReaction = $type;
	}

	/**
	 * @return string the type the current member reacted with, empty for none
	 */
	public function getMemberReaction(): string
	{
		if ($this->_memberReaction === null) {
			$post = $this->getForum()->getPosts()->findPost($this->getPostID());
			$reaction = $post ? $this->getForum()->getReactions()->getMemberReaction($post) : null;
			$this->_memberReaction = $reaction ? (string) $reaction->type : '';
		}
		return $this->_memberReaction;
	}

	/**
	 * @return array<string, string> the symbols keyed by type
	 */
	public function getSymbols(): array
	{
		return $this->_symbols;
	}

	/**
	 * @param array<string, string> $symbols symbols keyed by type, merged over the defaults
	 */
	public function setSymbols(array $symbols): void
	{
		$this->_symbols = array_merge(self::DEFAULT_SYMBOLS, $symbols);
	}

	/**
	 * @return bool whether the current user may react to the post
	 */
	public function getCanReact(): bool
	{
		$forum = $this->getForum();
		if (!$forum->getEnableReactions() || $this->getIsGuest() || $this->getPostID() <= 0) {
			return false;
		}
		$member = $this->getMember();
		if ($member !== null && $member->getId() === $this->getAuthorID()) {
			return false;
		}
		return $this->can(BEForumPermissions::REACT);
	}

	/**
	 * Builds the button rows (HTML escaped).
	 * @return array<int, array{type: string, symbol: string, label: string, count: int, active: bool}> the rows
	 */
	public function getRows(): array
	{
		$summary = $this->getSummary();
		$mine = $this->getMemberReaction();
		$rows = [];
		foreach ($this->getForum()->getReactionTypes() as $type) {
			$count = (int) ($summary[$type] ?? 0);
			$rows[] = [
				'type' => $type,
				'symbol' => $this->_symbols[$type] ?? $this->e($type),
				'label' => $this->e(ucfirst($type)),
				'count' => $count,
				'active' => $mine === $type,
			];
		}
		return $rows;
	}

	/**
	 * Toggles a reaction.
	 * @param mixed $sender the repeater
	 * @param TRepeaterCommandEventParameter $param the event parameter
	 */
	public function reactionCommand($sender, $param): void
	{
		$type = (string) $param->getCommandName();
		$this->attempt(function () use ($type): void {
			$post = $this->getForum()->getPosts()->getPost($this->getPostID());
			$this->getForum()->getReactions()->react($post, $type);
			$this->_summary = null;
			$this->_memberReaction = null;
		});
		// the buttons are active controls: a callback refreshes only this bar
		$this->_refresh = true;
		$this->updateOnCallback($this->Panel);
	}

	/**
	 * Shows the error here and, inside a post list, in the post row (the rows
	 * are rebuilt at pre-render, which would discard the local label).
	 * @param string $message the message
	 */
	public function showError(string $message): void
	{
		parent::showError($message);
		for ($control = $this->getParent(); $control !== null; $control = $control->getParent()) {
			if (method_exists($control, 'setPostError')) {
				$control->setPostError($this->getPostID(), $message);
				return;
			}
		}
	}

	/**
	 * Binds the buttons.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		if ($this->getIsCallback() && !$this->_refresh) {
			// another control's callback: keep the buttons restored from view state
			return;
		}
		$rows = $this->getRows();
		$canReact = $this->getCanReact();
		if (!$canReact) {
			$rows = array_values(array_filter($rows, fn (array $row) => $row['count'] > 0));
		}
		$this->setVisible($this->getForum()->getEnableReactions() && ($canReact || count($rows) > 0));
		$this->bindRepeater('Buttons', $canReact ? $rows : []);
		$this->bindRepeater('Counts', $canReact ? [] : $rows);
	}
}
