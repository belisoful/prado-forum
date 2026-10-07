<?php

/**
 * TForumRecentPosts class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;

/**
 * TForumRecentPosts shows a sidebar list of the most recent posts
 * across all boards, useful for sidebars and the forum home page.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumRecentPosts extends TForumControl
{
    private $_limit    = 10;

    /** @var TForumPostRecord[] */
    private $_posts = [];

    public function onLoad($param): void
    {
        parent::onLoad($param);
        $this->_posts = TForumPostRecord::finder()->with('author', 'thread')->findAll([
            'condition' => 'deleted_at IS NULL AND is_approved = 1',
            'order'     => 'created_at DESC',
            'limit'     => $this->_limit,
        ]) ?: [];
    }

    public function getPosts(): array { return $this->_posts; }


    public function getLimit(): int { return $this->_limit; }
    public function setLimit(int $v): void { $this->_limit = max(1, min(50, $v)); }

    public function getPostUrl(int $postId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['post' => $postId]) . '#post-' . $postId;
    }

}
