<?php

/**
 * TForumStats class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * TForumStats renders a sidebar summary of forum-wide statistics:
 * total threads, total posts, total members, newest member.
 *
 * Results are cached for 5 minutes when a cache component is available.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumStats extends TForumControl
{
    private $_stats    = null;

    public function onLoad($param): void
    {
        parent::onLoad($param);
        $this->_stats = $this->loadStats();
    }

    private function loadStats(): array
    {
        $cache = $this->getForumManager()->getCache();
        $key   = 'forum_global_stats';

        if ($cache && ($cached = $cache->get($key)) !== false) {
            return $cached;
        }

        $stats = [
            'threads'       => (int) TForumThreadRecord::finder()->count('deleted_at IS NULL AND is_approved = 1'),
            'posts'         => (int) TForumPostRecord::finder()->count('deleted_at IS NULL AND is_approved = 1'),
            'members'       => (int) TForumUserProfileRecord::finder()->count('is_active = 1'),
            'newest_member' => null,
        ];

        $newest = TForumUserProfileRecord::finder()->find(
            ['condition' => 'is_active = 1', 'order' => 'created_at DESC', 'limit' => 1]
        );
        if ($newest) {
            $stats['newest_member'] = [
                'username'     => $newest->username,
                'display_name' => $newest->getEffectiveDisplayName(),
            ];
        }

        if ($cache) {
            $cache->set($key, $stats, 300);
        }

        return $stats;
    }

    public function getStats(): array { return $this->_stats ?? []; }


    public function getMemberUrl(string $username): string
    {
        return $this->getForumManager()->createUrl('forum/UserProfile', ['username' => $username]);
    }

}
