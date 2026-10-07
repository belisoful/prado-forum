<?php

/**
 * UserProfile page class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumUserBadgeRecord;

/**
 * UserProfile displays a forum member's public profile.
 *
 * URL param: `?username=<username>` or `?id=<profileId>`
 * Tab param: `?tab=posts|threads|badges|about` (default: about)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class UserProfile extends TPage
{
    private $_moduleId = 'forum';

    /** @var TForumUserProfileRecord|null */
    private $_profile = null;

    /** @var string active tab */
    private $_tab = 'about';

    /** @var array tab-specific data */
    private $_tabData = [];

    public function onInit($param): void
    {
        parent::onInit($param);

        $username  = trim($this->getRequest()->getParam('username', ''));
        $profileId = (int) $this->getRequest()->getParam('id', 0);
        $this->_tab = $this->getRequest()->getParam('tab', 'about');

        if ($username !== '') {
            $this->_profile = TForumUserProfileRecord::finder()
                ->findByAttributes(['username' => $username]);
        } elseif ($profileId > 0) {
            $this->_profile = TForumUserProfileRecord::finder()->findByPk($profileId);
        }

        if ($this->_profile) {
            $this->setTitle($this->_profile->getEffectiveDisplayName() . ' — '
                . $this->getForumManager()->getSiteName());
            $this->loadTabData();
        }
    }

    private function loadTabData(): void
    {
        $fm      = $this->getForumManager();
        $profile = $this->_profile;

        switch ($this->_tab) {
            case 'posts':
                $this->_tabData['posts'] = TForumPostRecord::finder()->findAll([
                    'condition' => 'user_id = :uid AND deleted_at IS NULL AND is_approved = 1',
                    'params'    => [':uid' => $profile->id],
                    'order'     => 'created_at DESC',
                    'limit'     => $fm->getPostsPerPage(),
                ]) ?: [];
                break;

            case 'threads':
                $this->_tabData['threads'] = TForumThreadRecord::finder()->findAll([
                    'condition' => 'user_id = :uid AND deleted_at IS NULL AND is_approved = 1',
                    'params'    => [':uid' => $profile->id],
                    'order'     => 'created_at DESC',
                    'limit'     => $fm->getThreadsPerPage(),
                ]) ?: [];
                break;

            case 'badges':
                $this->_tabData['badges'] = TForumUserBadgeRecord::finder()
                    ->with('badge')
                    ->findAll('user_id = ?', [$profile->id]) ?: [];
                break;

            default: // 'about'
                $this->_tabData = [];
        }
    }

    public function getProfile(): ?TForumUserProfileRecord { return $this->_profile; }
    public function getActiveTab(): string { return $this->_tab; }
    public function getTabData(): array { return $this->_tabData; }

    public function getTabUrl(string $tab): string
    {
        return $this->getForumManager()->createUrl('forum/UserProfile', [
            'username' => $this->_profile ? $this->_profile->username : '',
            'tab'      => $tab,
        ]);
    }

    public function getThreadUrl(int $threadId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['id' => $threadId]);
    }

    public function getPostUrl(int $postId): string
    {
        return $this->getForumManager()->createUrl('forum/ThreadView', ['post' => $postId]) . '#post-' . $postId;
    }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
