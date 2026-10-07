<?php

/**
 * TForumRSSService class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Services;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumBoardRecord;
use Belisoful\Forum\ActiveRecord\TForumThreadRecord;
use Belisoful\Forum\ActiveRecord\TForumPostRecord;

/**
 * TForumRSSService generates Atom 1.0 and RSS 2.0 feeds for the forum.
 *
 * Available feeds:
 *   - Global recent threads (all boards)
 *   - Per-board recent threads
 *   - Per-thread recent posts (replies feed)
 *
 * Output is a complete XML document string ready to send with the
 * appropriate Content-Type header (application/atom+xml or
 * application/rss+xml).
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumRSSService
{
    /** @var TForumManager */
    private $_manager;

    /** @var int number of items to include per feed */
    private $_itemLimit = 25;

    public function __construct(TForumManager $manager)
    {
        $this->_manager = $manager;
    }

    // ===================================================================
    // Feed generators
    // ===================================================================

    /**
     * Generate an Atom 1.0 feed of the most recent threads across all boards.
     *
     * @return string Atom XML document
     */
    public function getGlobalThreadFeedAtom(): string
    {
        $threads = TForumThreadRecord::finder()->findAll([
            'condition' => 'deleted_at IS NULL AND is_approved = 1',
            'order'     => 'created_at DESC',
            'limit'     => $this->_itemLimit,
        ]);

        $feedUrl = $this->_manager->createUrl('forum/Feed', ['type' => 'global']);
        $siteUrl = $this->_manager->createUrl('forum/ForumHome');

        return $this->buildAtomFeed(
            $this->_manager->getSiteName() . ' — Recent Threads',
            $feedUrl,
            $siteUrl,
            $this->_manager->getSiteName() . ' forum discussion feed',
            $threads ?: [],
            'thread'
        );
    }

    /**
     * Generate an Atom 1.0 feed of recent threads in a specific board.
     *
     * @param int $boardId
     * @return string Atom XML document
     */
    public function getBoardThreadFeedAtom(int $boardId): string
    {
        $board = TForumBoardRecord::finder()->findByPk($boardId);
        if (!$board) {
            return $this->buildEmptyAtomFeed('Board not found');
        }

        $threads = TForumThreadRecord::finder()->findAll([
            'condition' => 'board_id = :bid AND deleted_at IS NULL AND is_approved = 1',
            'params'    => [':bid' => $boardId],
            'order'     => 'created_at DESC',
            'limit'     => $this->_itemLimit,
        ]);

        $feedUrl = $this->_manager->createUrl('forum/Feed', ['type' => 'board', 'id' => $boardId]);
        $siteUrl = $this->_manager->createUrl('forum/ForumView', ['id' => $boardId]);

        return $this->buildAtomFeed(
            $this->_manager->getSiteName() . ' — ' . htmlspecialchars($board->name, ENT_XML1),
            $feedUrl,
            $siteUrl,
            htmlspecialchars((string) $board->description, ENT_XML1),
            $threads ?: [],
            'thread'
        );
    }

    /**
     * Generate an Atom 1.0 feed of replies in a specific thread.
     *
     * @param int $threadId
     * @return string Atom XML document
     */
    public function getThreadPostFeedAtom(int $threadId): string
    {
        $thread = TForumThreadRecord::finder()->findByPk($threadId);
        if (!$thread) {
            return $this->buildEmptyAtomFeed('Thread not found');
        }

        $posts = TForumPostRecord::finder()->findAll([
            'condition' => 'thread_id = :tid AND deleted_at IS NULL AND is_approved = 1',
            'params'    => [':tid' => $threadId],
            'order'     => 'created_at DESC',
            'limit'     => $this->_itemLimit,
        ]);

        $feedUrl = $this->_manager->createUrl('forum/Feed', ['type' => 'thread', 'id' => $threadId]);
        $siteUrl = $this->_manager->createUrl('forum/ThreadView', ['id' => $threadId]);

        return $this->buildAtomFeed(
            $this->_manager->getSiteName() . ' — ' . htmlspecialchars($thread->title, ENT_XML1),
            $feedUrl,
            $siteUrl,
            'Replies in: ' . htmlspecialchars($thread->title, ENT_XML1),
            $posts ?: [],
            'post'
        );
    }

    /**
     * Generate an RSS 2.0 feed of recent threads (global).
     *
     * @return string RSS 2.0 XML document
     */
    public function getGlobalThreadFeedRSS(): string
    {
        $threads = TForumThreadRecord::finder()->findAll([
            'condition' => 'deleted_at IS NULL AND is_approved = 1',
            'order'     => 'created_at DESC',
            'limit'     => $this->_itemLimit,
        ]);

        $siteUrl = $this->_manager->createUrl('forum/ForumHome');

        return $this->buildRSSFeed(
            $this->_manager->getSiteName() . ' — Recent Threads',
            $siteUrl,
            $this->_manager->getSiteName() . ' forum discussion feed',
            $threads ?: [],
            'thread'
        );
    }

    // ===================================================================
    // Feed builders
    // ===================================================================

    /**
     * Build a complete Atom 1.0 XML feed document.
     *
     * @param string $title
     * @param string $feedUrl self-link
     * @param string $siteUrl alternate link
     * @param string $subtitle
     * @param array  $records TForumThreadRecord[] or TForumPostRecord[]
     * @param string $recordType 'thread' | 'post'
     * @return string
     */
    private function buildAtomFeed(
        string $title,
        string $feedUrl,
        string $siteUrl,
        string $subtitle,
        array $records,
        string $recordType
    ): string {
        $updated = !empty($records) ? $this->toAtomDate($records[0]->created_at) : date('c');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <title>' . htmlspecialchars($title, ENT_XML1) . '</title>' . "\n";
        $xml .= '  <subtitle>' . htmlspecialchars($subtitle, ENT_XML1) . '</subtitle>' . "\n";
        $xml .= '  <link href="' . htmlspecialchars($feedUrl, ENT_XML1) . '" rel="self"/>' . "\n";
        $xml .= '  <link href="' . htmlspecialchars($siteUrl, ENT_XML1) . '"/>' . "\n";
        $xml .= '  <updated>' . $updated . '</updated>' . "\n";
        $xml .= '  <id>' . htmlspecialchars($feedUrl, ENT_XML1) . '</id>' . "\n";
        $xml .= '  <generator>PRADO Forum Extension</generator>' . "\n";

        foreach ($records as $record) {
            $xml .= $recordType === 'thread'
                ? $this->buildAtomThreadEntry($record)
                : $this->buildAtomPostEntry($record);
        }

        $xml .= '</feed>';
        return $xml;
    }

    private function buildAtomThreadEntry(TForumThreadRecord $thread): string
    {
        $url     = $this->_manager->createUrl('forum/ThreadView', ['id' => $thread->id]);
        $date    = $this->toAtomDate($thread->created_at);
        $updated = $this->toAtomDate($thread->updated_at ?: $thread->created_at);

        return "  <entry>\n"
            . '    <id>' . htmlspecialchars($url, ENT_XML1) . "</id>\n"
            . '    <title>' . htmlspecialchars($thread->title, ENT_XML1) . "</title>\n"
            . '    <link href="' . htmlspecialchars($url, ENT_XML1) . "\"/>\n"
            . "    <published>$date</published>\n"
            . "    <updated>$updated</updated>\n"
            . "  </entry>\n";
    }

    private function buildAtomPostEntry(TForumPostRecord $post): string
    {
        $url     = $this->_manager->createUrl('forum/ThreadView', ['post' => $post->id]) . '#post-' . $post->id;
        $date    = $this->toAtomDate($post->created_at);
        $summary = mb_substr(strip_tags($post->content_html ?? ''), 0, 300);

        return "  <entry>\n"
            . '    <id>' . htmlspecialchars($url, ENT_XML1) . "</id>\n"
            . '    <title>Post #' . (int) $post->id . "</title>\n"
            . '    <link href="' . htmlspecialchars($url, ENT_XML1) . "\"/>\n"
            . "    <published>$date</published>\n"
            . "    <updated>$date</updated>\n"
            . '    <summary type="html"><![CDATA[' . $summary . "]]></summary>\n"
            . "  </entry>\n";
    }

    /**
     * Build a minimal RSS 2.0 XML feed document.
     */
    private function buildRSSFeed(
        string $title,
        string $siteUrl,
        string $description,
        array $records,
        string $recordType
    ): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>' . htmlspecialchars($title, ENT_XML1) . "</title>\n";
        $xml .= '    <link>' . htmlspecialchars($siteUrl, ENT_XML1) . "</link>\n";
        $xml .= '    <description>' . htmlspecialchars($description, ENT_XML1) . "</description>\n";
        $xml .= '    <language>en</language>' . "\n";
        $xml .= '    <lastBuildDate>' . date('r') . "</lastBuildDate>\n";

        foreach ($records as $record) {
            if ($recordType === 'thread') {
                $url  = $this->_manager->createUrl('forum/ThreadView', ['id' => $record->id]);
                $xml .= "    <item>\n"
                    . '      <title>' . htmlspecialchars($record->title, ENT_XML1) . "</title>\n"
                    . '      <link>' . htmlspecialchars($url, ENT_XML1) . "</link>\n"
                    . '      <guid isPermaLink="true">' . htmlspecialchars($url, ENT_XML1) . "</guid>\n"
                    . '      <pubDate>' . date('r', strtotime($record->created_at)) . "</pubDate>\n"
                    . "    </item>\n";
            }
        }

        $xml .= "  </channel>\n</rss>";
        return $xml;
    }

    private function buildEmptyAtomFeed(string $reason): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<feed xmlns="http://www.w3.org/2005/Atom">'
            . '<title>' . htmlspecialchars($reason, ENT_XML1) . '</title>'
            . '<id>urn:empty</id>'
            . '<updated>' . date('c') . '</updated>'
            . '</feed>';
    }

    private function toAtomDate(?string $mysqlDatetime): string
    {
        if (!$mysqlDatetime) {
            return date('c');
        }
        return date('c', strtotime($mysqlDatetime));
    }

    // ===================================================================
    // Configuration
    // ===================================================================

    public function getItemLimit(): int { return $this->_itemLimit; }
    public function setItemLimit(int $v): void { $this->_itemLimit = max(1, min(100, $v)); }
}
