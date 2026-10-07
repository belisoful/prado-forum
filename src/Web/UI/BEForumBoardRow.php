<?php

/**
 * BEForumBoardRow class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

/**
 * BEForumBoardRow class.
 *
 * BEForumBoardRow renders one board of the {@see BEForumCategoryList}: name,
 * flags, description, sub boards, counters and the last post.  The row keys
 * are documented in {@see BEForumCategoryList::boardRow}.
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $SubBoards
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumBoardRow extends BEForumItemRenderer
{
	/**
	 * Binds the sub boards.
	 */
	protected function bindChildRepeaters(): void
	{
		$this->bindChildRepeater('SubBoards', 'subBoards');
	}

	/**
	 * @return string the last post cell HTML
	 */
	public function getLastPostHtml(): string
	{
		if ($this->item('lastTitle', '') === '') {
			return '<span class="' . $this->css('muted') . '">' . $this->te('No posts yet') . '</span>';
		}
		return '<a class="' . $this->css('board-last-title') . '" href="' . $this->item('lastUrl') . '">' . $this->item('lastTitle') . '</a>'
			. '<span class="' . $this->css('board-last-meta') . '">' . $this->item('lastPoster') . ' ' . $this->item('lastTime') . '</span>';
	}

	/**
	 * @return string the flag icons HTML
	 */
	public function getFlagsHtml(): string
	{
		$html = '';
		if ($this->is('locked')) {
			$html .= $this->flag('locked', '&#128274;', $this->t('Locked'));
		}
		if ($this->is('private')) {
			$html .= $this->flag('private', '&#128100;', $this->t('Members only'));
		}
		return $html;
	}
}
