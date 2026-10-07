<?php

/**
 * BEForumThreadRow class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

/**
 * BEForumThreadRow class.
 *
 * BEForumThreadRow renders one thread of a {@see BEForumThreadList}: flags,
 * title, tags, author, counters, last post and unread link.  The row keys are
 * documented in {@see BEForumThreadList::threadRow}.
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Tags
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumThreadRow extends BEForumItemRenderer
{
	/**
	 * Binds the tags.
	 */
	protected function bindChildRepeaters(): void
	{
		$this->bindChildRepeater('Tags', 'tags');
	}

	/**
	 * @return string the row modifier classes
	 */
	public function getRowCss(): string
	{
		$classes = [$this->css('thread')];
		foreach (['pinned', 'locked', 'solved', 'unread', 'deleted', 'pending'] as $modifier) {
			if ($this->is($modifier)) {
				$classes[] = $this->css('thread', $modifier);
			}
		}
		if ($this->item('type', '') !== '') {
			$classes[] = $this->css('thread', 'type-' . $this->item('type'));
		}
		return implode(' ', array_unique($classes));
	}

	/**
	 * @return string the flag icons HTML
	 */
	public function getFlagsHtml(): string
	{
		$html = '';
		if ($this->is('pinned')) {
			$html .= $this->flag('pinned', '&#128204;', $this->t('Pinned'));
		}
		if ($this->is('locked')) {
			$html .= $this->flag('locked', '&#128274;', $this->t('Locked'));
		}
		if ($this->is('solved')) {
			$html .= $this->flag('solved', '&#10004;', $this->t('Solved'));
		}
		if ($this->is('pending')) {
			$html .= $this->flag('pending', '&#9203;', $this->t('Awaiting approval'));
		}
		if ($this->is('deleted')) {
			$html .= $this->flag('deleted', '&#128465;', $this->t('Deleted'));
		}
		if ($this->item('typeLabel', '') !== '') {
			$html .= '<span class="' . $this->css('type', $this->item('type')) . '">' . $this->item('typeLabel') . '</span>';
		}
		return $html;
	}

	/**
	 * @return string the unread jump link HTML
	 */
	public function getUnreadHtml(): string
	{
		if (!$this->is('unread') || $this->item('unreadUrl', '') === '') {
			return '';
		}
		return '<a class="' . $this->css('thread-unread') . '" href="' . $this->item('unreadUrl') . '" title="' . $this->te('Go to first unread post') . '">&#9679;</a>';
	}

	/**
	 * @return string the board link HTML when the list spans boards
	 */
	public function getBoardHtml(): string
	{
		if ($this->item('boardName', '') === '') {
			return '';
		}
		return '<span class="' . $this->css('thread-board') . '">' . $this->te('in') . ' <a href="' . $this->item('boardUrl') . '">' . $this->item('boardName') . '</a></span>';
	}

	/**
	 * @return string the last post cell HTML
	 */
	public function getLastPostHtml(): string
	{
		if ($this->item('lastTime', '') === '') {
			return '<span class="' . $this->css('muted') . '">&mdash;</span>';
		}
		return '<a class="' . $this->css('thread-last-link') . '" href="' . $this->item('lastUrl') . '">' . $this->item('lastTime') . '</a>'
			. '<span class="' . $this->css('thread-last-poster') . '">' . $this->item('lastPoster') . '</span>';
	}
}
