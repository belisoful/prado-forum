<?php

/**
 * TForumNewThread class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Portlets;

use Prado\Web\UI\WebControls\TButton;
use Prado\Web\UI\WebControls\TTextBox;
use Belisoful\Forum\TForumManager;
use Belisoful\Forum\Web\UI\TForumControl;
use Belisoful\Forum\ActiveRecord\TForumBoardRecord;
use Belisoful\Forum\ActiveRecord\TForumTagRecord;

/**
 * TForumNewThread renders the "Create New Thread" form.
 *
 * The portlet handles both the GET (display) and POST (submit) cases,
 * delegating persistence to the host page via the `OnThreadCreated` event.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumNewThread extends TForumControl
{
    private $_boardId  = 0;

    /** @var string[] validation error messages */
    private $_errors = [];

    /** @var bool whether the form was just submitted with errors */
    private $_hasErrors = false;

    public function onLoad($param): void
    {
        parent::onLoad($param);
        // Track form submission start time for spam detection.
        if (!$this->getPage()->getIsPostBack()) {
            $this->getPage()->getSession()->add('forum_form_start', time());
        }
    }

    // ===================================================================
    // Event handlers wired via template
    // ===================================================================

    /** Called when the submit button is clicked. */
    public function submitThread($sender, $param): void
    {
        $fm      = $this->getForumManager();
        $request = $this->getRequest();

        $title   = trim($request->getParam('forum_title', ''));
        $body    = trim($request->getParam('forum_body', ''));
        $tagsCsv = trim($request->getParam('forum_tags', ''));
        $elapsed = time() - (int) $this->getPage()->getSession()->get('forum_form_start', 0);

        $userModule = $this->getApplication()->getModule('users');
        $username   = $userModule ? $userModule->getUser()->getName() : 'guest';

        // Validate.
        if (mb_strlen($title) < 5) {
            $this->_errors[] = 'Title must be at least 5 characters.';
        }
        if (mb_strlen($title) > $fm->getMaxTitleLength()) {
            $this->_errors[] = 'Title is too long (max ' . $fm->getMaxTitleLength() . ' chars).';
        }
        if (mb_strlen($body) < $fm->getMinPostLength()) {
            $this->_errors[] = 'Post body must be at least ' . $fm->getMinPostLength() . ' characters.';
        }
        if (mb_strlen($body) > $fm->getMaxPostLength()) {
            $this->_errors[] = 'Post body is too long.';
        }

        // Spam check.
        if ($fm->getEnableSpamProtection()) {
            $eval = $fm->getSpamService()->evaluate($title, $body, $username, $elapsed);
            if ($eval['flagged']) {
                $this->_errors[] = 'Your post has been flagged as potential spam. Please revise it.';
            }
        }

        // Rate limit check.
        if ($fm->isRateLimited('thread', $username)) {
            $this->_errors[] = 'You are posting too fast. Please wait before creating another thread.';
        }

        $this->_hasErrors = !empty($this->_errors);

        if (!$this->_hasErrors) {
            // Raise event for the host page to handle persistence.
            $this->raiseEvent('OnThreadCreated', $this, [
                'board_id' => $this->_boardId,
                'title'    => $title,
                'body'     => $body,
                'tags'     => array_filter(array_map('trim', explode(',', $tagsCsv))),
                'username' => $username,
                'elapsed'  => $elapsed,
            ]);
        }
    }

    // ===================================================================
    // Properties
    // ===================================================================


    public function getBoardId(): int { return $this->_boardId; }
    public function setBoardId(int $v): void { $this->_boardId = $v; }

    public function getErrors(): array { return $this->_errors; }
    public function getHasErrors(): bool { return $this->_hasErrors; }

    public function getBoard(): ?TForumBoardRecord
    {
        return $this->_boardId > 0 ? TForumBoardRecord::finder()->findByPk($this->_boardId) : null;
    }


    public function getEnableTags(): bool
    {
        return $this->getForumManager()->getEnableTags();
    }

    public function getEnablePolls(): bool
    {
        return $this->getForumManager()->getEnablePolls();
    }

    public function getMaxTitleLength(): int
    {
        return $this->getForumManager()->getMaxTitleLength();
    }

    public function getMaxPostLength(): int
    {
        return $this->getForumManager()->getMaxPostLength();
    }
}
