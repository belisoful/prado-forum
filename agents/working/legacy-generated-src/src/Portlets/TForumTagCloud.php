<?php

/**
 * TForumTagCloud class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumTagRecord;

/**
 * TForumTagCloud renders a weighted tag cloud.
 *
 * Tag font-size is scaled between MinSize and MaxSize (em units) relative
 * to the thread_count of each tag.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumTagCloud extends TForumControl
{
    private $_limit    = 50;
    private $_minSize  = 0.8;  // em
    private $_maxSize  = 2.2;  // em

    /** @var TForumTagRecord[] */
    private $_tags = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);
        $this->_tags = TForumTagRecord::finder()->findAll([
            'condition' => 'thread_count > 0',
            'order'     => 'thread_count DESC',
            'limit'     => $this->_limit,
        ]) ?: [];
    }

    public function getTags(): array { return $this->_tags; }

    /** Calculate the em size for a tag based on its thread_count. */
    public function getTagSize(TForumTagRecord $tag): float
    {
        if (count($this->_tags) <= 1) {
            return $this->_minSize;
        }
        $counts = array_map(fn($t) => (int) $t->thread_count, $this->_tags);
        $min    = min($counts);
        $max    = max($counts);
        if ($max === $min) {
            return round(($this->_minSize + $this->_maxSize) / 2, 2);
        }
        $ratio = ((int) $tag->thread_count - $min) / ($max - $min);
        return round($this->_minSize + $ratio * ($this->_maxSize - $this->_minSize), 2);
    }

    public function getTagUrl(string $slug): string
    {
        return $this->getForumManager()->createUrl('forum/Search', ['tag' => $slug]);
    }


    public function getLimit(): int { return $this->_limit; }
    public function setLimit(int $v): void { $this->_limit = max(1, $v); }

}
