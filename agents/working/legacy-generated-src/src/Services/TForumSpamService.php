<?php

/**
 * TForumSpamService class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Services;

use Belisoful\Forum\TForumManager;
use Belisoful\Forum\ActiveRecord\TForumUserProfileRecord;

/**
 * TForumSpamService scores submitted content using heuristic rules and
 * decides whether a post should be held for moderation or auto-rejected.
 *
 * Scoring model (points are additive; higher = more spammy):
 *
 *   +3  Post contains more than 3 bare URLs
 *   +2  Post body is shorter than 15 characters
 *   +2  The same word or phrase appears more than 5 times consecutively
 *   +2  Post contains a known spam keyword phrase
 *   +1  Posting user's account is less than 24 hours old
 *   +1  Posting user has zero prior approved posts
 *   +2  Username matches a typical bot pattern (all-random alphanumeric)
 *   +3  Post was submitted in under 3 seconds (bot speed)
 *   +1  Title and body are identical (lazy duplicate)
 *   +2  Content contains a suspicious link shortener domain
 *
 * The score is compared to {@see TForumManager::getSpamThreshold()}.
 * Posts at or above the threshold are flagged.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumSpamService
{
    /** @var TForumManager */
    private $_manager;

    /** @var string[] known spam keyword phrases (lowercase) */
    private static $_spamPhrases = [
        'buy cheap', 'click here now', 'earn money fast', 'free viagra',
        'casino online', 'weight loss', 'make money online', 'work from home',
        'limited time offer', 'act now', 'guaranteed winner', 'no risk',
        'cryptocurrency investment', 'double your bitcoin',
    ];

    /** @var string[] known link shortener / suspicious domains */
    private static $_suspiciousDomains = [
        'bit.ly', 'tinyurl.com', 'goo.gl', 'ow.ly', 'adf.ly',
        'linkbucks.com', 'adfly.com', 'bc.vc', 'clk.sh',
    ];

    public function __construct(TForumManager $manager)
    {
        $this->_manager = $manager;
    }

    // ===================================================================
    // Public API
    // ===================================================================

    /**
     * Score a submitted post.
     *
     * @param string $title         thread title or empty string for replies
     * @param string $body          raw post content
     * @param string $username      posting user's username
     * @param float  $elapsedSecs   seconds taken to fill out and submit the form
     * @return int spam score (0 = clean, higher = more suspicious)
     */
    public function score(string $title, string $body, string $username, float $elapsedSecs = 60.0): int
    {
        $score = 0;
        $lower = mb_strtolower($body);

        $score += $this->scoreUrlDensity($body);
        $score += $this->scoreBodyLength($body);
        $score += $this->scoreRepetition($lower);
        $score += $this->scoreSpamPhrases($lower);
        $score += $this->scoreUserAge($username);
        $score += $this->scoreZeroPosts($username);
        $score += $this->scoreBotUsername($username);
        $score += $this->scoreSubmissionSpeed($elapsedSecs);
        $score += $this->scoreTitleBodyDuplicate($title, $body);
        $score += $this->scoreSuspiciousDomains($body);

        return max(0, $score);
    }

    /**
     * Determine whether the given score exceeds the spam threshold.
     *
     * @param int $score result of {@see score()}
     * @return bool true when the content should be flagged as spam
     */
    public function isFlagged(int $score): bool
    {
        return $score >= $this->_manager->getSpamThreshold();
    }

    /**
     * All-in-one: score content and return whether it should be flagged.
     *
     * @param string $title
     * @param string $body
     * @param string $username
     * @param float  $elapsedSecs
     * @return array{score: int, flagged: bool}
     */
    public function evaluate(string $title, string $body, string $username, float $elapsedSecs = 60.0): array
    {
        $s = $this->score($title, $body, $username, $elapsedSecs);
        return ['score' => $s, 'flagged' => $this->isFlagged($s)];
    }

    // ===================================================================
    // Scoring rules
    // ===================================================================

    /** +3 when body contains more than 3 bare URLs */
    private function scoreUrlDensity(string $body): int
    {
        $count = preg_match_all('/https?:\/\//i', $body);
        return $count > 3 ? 3 : 0;
    }

    /** +2 when post body (stripped) is fewer than 15 chars */
    private function scoreBodyLength(string $body): int
    {
        return mb_strlen(strip_tags(trim($body))) < 15 ? 2 : 0;
    }

    /** +2 when any word/token appears more than 5 times in a row */
    private function scoreRepetition(string $lower): int
    {
        if (preg_match('/(\b\w+\b)(\s+\1){5,}/i', $lower)) {
            return 2;
        }
        return 0;
    }

    /** +2 for every known spam phrase found */
    private function scoreSpamPhrases(string $lower): int
    {
        $found = 0;
        foreach (self::$_spamPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                $found++;
            }
        }
        return min($found * 2, 6);
    }

    /** +1 when the account is less than 24 hours old */
    private function scoreUserAge(string $username): int
    {
        $profile = TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]);
        if ($profile && $profile->created_at) {
            $ageHours = (time() - strtotime($profile->created_at)) / 3600;
            return $ageHours < 24 ? 1 : 0;
        }
        return 1; // unknown user = slightly suspicious
    }

    /** +1 when the user has zero approved posts */
    private function scoreZeroPosts(string $username): int
    {
        $profile = TForumUserProfileRecord::finder()->findByAttributes(['username' => $username]);
        return ($profile && $profile->post_count == 0) ? 1 : 0;
    }

    /** +2 when username looks like a random bot string (≥10 alphanum chars, no vowel) */
    private function scoreBotUsername(string $username): int
    {
        // Classic bot pattern: long run of consonants/digits with no vowels.
        if (strlen($username) >= 10 && !preg_match('/[aeiou]/i', $username)) {
            return 2;
        }
        // Pure random alphanumeric with digits interspersed throughout.
        if (preg_match('/^[a-z]+\d{4,}$/i', $username)) {
            return 1;
        }
        return 0;
    }

    /** +3 when form was submitted in under 3 seconds (bot speed) */
    private function scoreSubmissionSpeed(float $elapsedSecs): int
    {
        return $elapsedSecs < 3.0 ? 3 : 0;
    }

    /** +1 when a non-empty title exactly matches the body */
    private function scoreTitleBodyDuplicate(string $title, string $body): int
    {
        if ($title !== '' && trim($title) === trim($body)) {
            return 1;
        }
        return 0;
    }

    /** +2 for each known suspicious link-shortener domain found */
    private function scoreSuspiciousDomains(string $body): int
    {
        $found = 0;
        $lower = strtolower($body);
        foreach (self::$_suspiciousDomains as $domain) {
            if (str_contains($lower, $domain)) {
                $found++;
            }
        }
        return min($found * 2, 4);
    }
}
