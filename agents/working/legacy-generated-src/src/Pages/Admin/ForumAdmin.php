<?php

/**
 * ForumAdmin page class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Pages\Admin;

use Prado\Web\UI\TPage;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumCategoryRecord;
use Belisoful\Forum\ActiveRecord\TForumBoardRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;
use Belisoful\Forum\ActiveRecord\TForumModerationRecord;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;
use Belisoful\Forum\ActiveRecord\TForumTagRecord;

/**
 * ForumAdmin is the main forum administration panel.
 *
 * Requires the current user to have the 'ForumAdmin' role.
 *
 * Panels:
 *   - Dashboard: counts, recent moderation actions, pending posts
 *   - Categories: create/edit/reorder/delete categories
 *   - Boards: create/edit/lock/delete boards
 *   - Users: search, ban, warn, edit profiles
 *   - Settings: overview of TForumManager configuration
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class ForumAdmin extends TPage
{
    private $_moduleId = 'forum';
    private $_panel    = 'dashboard';

    /** @var array dashboard summary data */
    private $_dashboard = [];

    /** @var TForumCategoryRecord[] */
    private $_categories = [];

    /** @var TForumBoardRecord[] */
    private $_boards = [];

    /** @var TForumUserProfileRecord[] search results */
    private $_userList = [];

    /** @var TForumPostRecord[] pending-approval posts */
    private $_pendingPosts = [];

    /** @var TForumPostRecord[] spam-flagged posts */
    private $_spamPosts = [];

    public function onInit($param): void
    {
        parent::onInit($param);

        // Access control.
        $users = $this->getApplication()->getModule('users');
        if (!$users || !$users->isForumAdmin()) {
            $this->getResponse()->redirect(
                $this->getForumManager()->createUrl('forum/ForumHome')
            );
            return;
        }

        $this->_panel = $this->getRequest()->getParam('panel', 'dashboard');
        $this->setTitle('Admin — ' . $this->getForumManager()->getSiteName());

        switch ($this->_panel) {
            case 'categories':
                $this->_categories = TForumCategoryRecord::finder()->with('boards')->findAll(
                    ['order' => 'sort_order ASC']
                ) ?: [];
                break;
            case 'boards':
                $this->_boards = TForumBoardRecord::finder()->with('category')->findAll(
                    ['order' => 'sort_order ASC']
                ) ?: [];
                // Also load categories for the add-board form
                $this->_categories = TForumCategoryRecord::finder()->findAll(
                    ['order' => 'sort_order ASC']
                ) ?: [];
                break;
            case 'users':
                $this->loadUserSearch();
                break;
            case 'moderation':
                $this->loadModerationQueues();
                break;
            default:
                $this->loadDashboard();
        }
    }

    // ===================================================================
    // Dashboard
    // ===================================================================

    private function loadDashboard(): void
    {
        $db = $this->getForumManager()->getDbConnection();
        $fm = $this->getForumManager();

        $this->_dashboard = [
            'total_threads'  => (int) $db->createCommand("SELECT COUNT(*) FROM {$fm->getTable('threads')} WHERE deleted_at IS NULL")->queryScalar(),
            'total_posts'    => (int) $db->createCommand("SELECT COUNT(*) FROM {$fm->getTable('posts')} WHERE deleted_at IS NULL")->queryScalar(),
            'total_users'    => (int) $db->createCommand("SELECT COUNT(*) FROM {$fm->getTable('user_profiles')} WHERE is_active = 1")->queryScalar(),
            'pending_posts'  => (int) $db->createCommand("SELECT COUNT(*) FROM {$fm->getTable('posts')} WHERE is_approved = 0 AND deleted_at IS NULL")->queryScalar(),
            'spam_flagged'   => (int) $db->createCommand("SELECT COUNT(*) FROM {$fm->getTable('posts')} WHERE is_spam_flagged = 1 AND deleted_at IS NULL")->queryScalar(),
            'banned_users'   => (int) $db->createCommand("SELECT COUNT(*) FROM {$fm->getTable('user_profiles')} WHERE is_banned = 1")->queryScalar(),
            'recent_mod_log' => TForumModerationRecord::finder()->with('moderator')->findAll([
                'order' => 'created_at DESC',
                'limit' => 10,
            ]) ?: [],
        ];
    }

    // ===================================================================
    // Data loaders
    // ===================================================================

    private function loadUserSearch(): void
    {
        $q = trim($this->getRequest()->getParam('uq', ''));
        if ($q === '') {
            return;
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $this->_userList = TForumUserProfileRecord::finder()->findAll([
            'condition' => 'username LIKE :q OR display_name LIKE :q',
            'params'    => [':q' => $like],
            'order'     => 'created_at DESC',
            'limit'     => 50,
        ]) ?: [];
    }

    private function loadModerationQueues(): void
    {
        $fm = $this->getForumManager();
        $this->_pendingPosts = TForumPostRecord::finder()->with('author')->findAll([
            'condition' => 'is_approved = 0 AND deleted_at IS NULL',
            'order'     => 'created_at ASC',
            'limit'     => 50,
        ]) ?: [];
        $this->_spamPosts = TForumPostRecord::finder()->with('author')->findAll([
            'condition' => 'is_spam_flagged = 1 AND deleted_at IS NULL',
            'order'     => 'spam_score DESC',
            'limit'     => 50,
        ]) ?: [];
    }

    // ===================================================================
    // Action handlers
    // ===================================================================

    public function saveCategory($sender, $param): void
    {
        $request  = $this->getRequest();
        $id       = (int) $request->getParam('cat_id', 0);
        $name     = trim($request->getParam('cat_name', ''));
        $desc     = trim($request->getParam('cat_desc', ''));
        $order    = (int) $request->getParam('cat_order', 0);
        $isActive = (int) (bool) $request->getParam('cat_active', 1);

        if ($name === '') {
            return;
        }

        $cat = $id > 0
            ? TForumCategoryRecord::finder()->findByPk($id) ?? new TForumCategoryRecord()
            : new TForumCategoryRecord();

        $cat->name        = $name;
        $cat->description = $desc;
        $cat->sort_order  = $order;
        $cat->is_active   = $isActive;
        if (!$id) {
            $cat->created_at = date('Y-m-d H:i:s');
        }
        $cat->updated_at = date('Y-m-d H:i:s');
        $cat->save();

        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => 'categories'])
        );
    }

    public function deleteCategory($sender, $param): void
    {
        $id = (int) $this->getRequest()->getParam('cat_id', 0);
        if ($id > 0) {
            $cat = TForumCategoryRecord::finder()->findByPk($id);
            if ($cat) {
                $cat->delete();
            }
        }
        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => 'categories'])
        );
    }

    public function saveBoard($sender, $param): void
    {
        $request  = $this->getRequest();
        $id       = (int) $request->getParam('board_id', 0);
        $catId    = (int) $request->getParam('board_cat_id', 0);
        $name     = trim($request->getParam('board_name', ''));
        $desc     = trim($request->getParam('board_desc', ''));
        $slug     = trim($request->getParam('board_slug', ''));
        $order    = (int) $request->getParam('board_order', 0);
        $isActive = (int) (bool) $request->getParam('board_active', 1);
        $isLocked = (int) (bool) $request->getParam('board_locked', 0);

        if ($name === '' || $catId <= 0) {
            return;
        }

        $board = $id > 0
            ? TForumBoardRecord::finder()->findByPk($id) ?? new TForumBoardRecord()
            : new TForumBoardRecord();

        $board->category_id  = $catId;
        $board->name         = $name;
        $board->description  = $desc;
        $board->slug         = $slug !== '' ? $slug : TForumTagRecord::slugify($name);
        $board->sort_order   = $order;
        $board->is_active    = $isActive;
        $board->is_locked    = $isLocked;
        if (!$id) {
            $board->created_at = date('Y-m-d H:i:s');
        }
        $board->updated_at = date('Y-m-d H:i:s');
        $board->save();

        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => 'boards'])
        );
    }

    public function deleteBoard($sender, $param): void
    {
        $id = (int) $this->getRequest()->getParam('board_id', 0);
        if ($id > 0) {
            $board = TForumBoardRecord::finder()->findByPk($id);
            if ($board) {
                $board->delete();
            }
        }
        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => 'boards'])
        );
    }

    public function moderateUser($sender, $param): void
    {
        $id     = (int) $this->getRequest()->getParam('user_id', 0);
        $action = $this->getRequest()->getParam('user_action', '');
        if ($id > 0) {
            $profile = TForumUserProfileRecord::finder()->findByPk($id);
            if ($profile) {
                if ($action === 'ban') {
                    $profile->is_banned = 1;
                    $profile->banned_until = null; // permanent
                    $profile->save();
                } elseif ($action === 'unban') {
                    $profile->is_banned = 0;
                    $profile->banned_until = null;
                    $profile->save();
                }
            }
        }
        $q = $this->getRequest()->getParam('uq', '');
        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => 'users', 'uq' => $q])
        );
    }

    public function moderatePost($sender, $param): void
    {
        $id     = (int) $this->getRequest()->getParam('post_id', 0);
        $action = $this->getRequest()->getParam('mod_action', '');
        if ($id > 0) {
            $post = TForumPostRecord::finder()->findByPk($id);
            if ($post) {
                if ($action === 'approve') {
                    $post->is_approved = 1;
                    $post->save();
                } elseif ($action === 'clear_spam') {
                    $post->is_spam_flagged = 0;
                    $post->spam_score = 0;
                    $post->is_approved = 1;
                    $post->save();
                } elseif ($action === 'delete') {
                    $post->deleted_at = date('Y-m-d H:i:s');
                    $post->save();
                }
            }
        }
        $this->getResponse()->redirect(
            $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => 'moderation'])
        );
    }

    // ===================================================================
    // Properties
    // ===================================================================

    public function getPanel(): string { return $this->_panel; }
    public function getDashboard(): array { return $this->_dashboard; }
    public function getCategories(): array { return $this->_categories; }
    public function getBoards(): array { return $this->_boards; }
    public function getUserList(): array { return $this->_userList; }
    public function getPendingPosts(): array { return $this->_pendingPosts; }
    public function getSpamPosts(): array { return $this->_spamPosts; }

    public function getPanelUrl(string $panel): string
    {
        return $this->getForumManager()->createUrl('forum/Admin/ForumAdmin', ['panel' => $panel]);
    }

    public function getForumManager(): TForumManager
    {
        return $this->getApplication()->getModule($this->_moduleId);
    }

    public function getModuleId(): string { return $this->_moduleId; }
    public function setModuleId(string $v): void { $this->_moduleId = $v; }
}
