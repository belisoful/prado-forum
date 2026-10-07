<?php

/**
 * BEForumStatistics class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

/**
 * BEForumStatistics class.
 *
 * BEForumStatistics shows the forum wide figures: threads, posts, members,
 * newest member and members online, using the cached summary of
 * {@see \Belisoful\Forum\Managers\BEForumStatisticsManager}.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumStatistics />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumStatistics extends BEForumControl
{
	/** @var null|array the summary */
	private ?array $_summary = null;

	/**
	 * @return array the statistics summary
	 */
	public function getSummary(): array
	{
		if ($this->_summary === null) {
			$this->_summary = $this->getForum()->getStatistics()->getSummary();
		}
		return $this->_summary;
	}

	/**
	 * @return array<int, array{label: string, value: string}> the figures (HTML escaped)
	 */
	public function getRows(): array
	{
		$summary = $this->getSummary();
		$rows = [
			['label' => $this->te('Threads'), 'value' => $this->e((string) $summary['threads'])],
			['label' => $this->te('Posts'), 'value' => $this->e((string) $summary['posts'])],
			['label' => $this->te('Members'), 'value' => $this->e((string) $summary['members'])],
			['label' => $this->te('Online'), 'value' => $this->e((string) $summary['online'])],
		];
		if (!empty($summary['newest_member'])) {
			$rows[] = ['label' => $this->te('Newest member'), 'value' => '<a href="' . $this->e($this->getUrls()->member((string) $summary['newest_member_username'])) . '">' . $this->e((string) $summary['newest_member']) . '</a>'];
		}
		return $rows;
	}

	/**
	 * Binds the figures.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$this->bindRepeater('Rows', $this->getRows());
	}
}
