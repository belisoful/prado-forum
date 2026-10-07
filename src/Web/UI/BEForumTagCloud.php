<?php

/**
 * BEForumTagCloud class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Web\UI;

use Prado\TPropertyValue;

/**
 * BEForumTagCloud class.
 *
 * BEForumTagCloud renders the most used tags, weighted into five size classes
 * (`--w1` to `--w5`) by thread count.
 * ```xml
 * <com:Belisoful\Forum\Web\UI\BEForumTagCloud Limit="30" />
 * ```
 *
 * @property \Prado\Web\UI\WebControls\TRepeater $Rows
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumTagCloud extends BEForumControl
{
	/**
	 * @return int the maximum number of tags
	 */
	public function getLimit(): int
	{
		return (int) $this->getViewState('Limit', 30);
	}

	/**
	 * @param int $limit the maximum number of tags
	 */
	public function setLimit($limit): void
	{
		$this->setViewState('Limit', max(1, TPropertyValue::ensureInteger($limit)), 30);
	}

	/**
	 * Builds the rows (HTML escaped), sorted by name.
	 * @return array<int, array{name: string, url: string, count: int, weight: int}> the rows
	 */
	public function getRows(): array
	{
		$forum = $this->getForum();
		if (!$forum->getEnableTags()) {
			return [];
		}
		$tags = $forum->getTags()->getPopularTags($this->getLimit());
		if (!$tags) {
			return [];
		}
		$max = max(array_map(fn ($tag) => (int) $tag->thread_count, $tags));
		$min = min(array_map(fn ($tag) => (int) $tag->thread_count, $tags));
		$rows = [];
		foreach ($tags as $tag) {
			$count = (int) $tag->thread_count;
			$weight = $max === $min ? 3 : 1 + (int) floor(($count - $min) * 4 / max(1, $max - $min));
			$rows[] = [
				'name' => $this->e((string) $tag->name),
				'url' => $this->e($this->getUrls()->tag($tag)),
				'count' => $count,
				'weight' => max(1, min(5, $weight)),
			];
		}
		usort($rows, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
		return $rows;
	}

	/**
	 * Binds the rows.
	 * @param mixed $param the event parameter
	 */
	public function onPreRender($param)
	{
		parent::onPreRender($param);
		$rows = $this->getRows();
		$this->setVisible(count($rows) > 0);
		$this->bindRepeater('Rows', $rows);
	}
}
