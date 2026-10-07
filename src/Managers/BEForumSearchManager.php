<?php

/**
 * BEForumSearchManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumPost;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Util\BEForumPagination;
use Prado\Data\ActiveRecord\TActiveRecordCriteria;

/**
 * BEForumSearchManager class.
 *
 * BEForumSearchManager searches thread titles and post contents.  The built
 * in implementation tokenises the query and requires every word to match
 * (case insensitive `LIKE`), which works on every database.  Hosts with a
 * full text index replace the criteria through `dySearchCriteria`.
 *
 * ```php
 * [$posts, $pagination] = $forum->getSearch()->searchPosts('prado forum', ['board' => $board]);
 * [$threads, $pagination] = $forum->getSearch()->searchThreads('prado');
 * ```
 *
 * @method TActiveRecordCriteria dySearchCriteria(TActiveRecordCriteria $criteria, string $query, string[] $words, string $scope, array $options)
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumSearchManager extends BEForumManager
{
	public const SCOPE_POSTS = 'posts';
	public const SCOPE_THREADS = 'threads';

	/** The maximum number of words used from a query */
	public const MAX_WORDS = 8;

	/**
	 * Splits a query into search words.
	 * @param string $query the query
	 * @throws BEForumValidationException when the query is too short
	 * @return string[] the lower cased words, at most {@see MAX_WORDS}
	 */
	public function parseQuery(string $query): array
	{
		$query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
		if (mb_strlen($query) < $this->getModule()->getSearchMinLength()) {
			throw new BEForumValidationException('q', 'forum_search_too_short', $this->getModule()->getSearchMinLength());
		}
		$words = [];
		foreach (explode(' ', mb_strtolower($query)) as $word) {
			$word = trim($word, " \t\"'");
			if ($word !== '' && !in_array($word, $words, true)) {
				$words[] = $word;
			}
			if (count($words) >= self::MAX_WORDS) {
				break;
			}
		}
		if (!$words) {
			throw new BEForumValidationException('q', 'forum_search_too_short', $this->getModule()->getSearchMinLength());
		}
		return $words;
	}

	/**
	 * Builds the LIKE conditions of the words over columns.
	 * @param string[] $words the words
	 * @param string[] $columns the columns
	 * @param array $params by reference, receives the bound parameters
	 * @return string the condition
	 */
	protected function buildWordCondition(array $words, array $columns, array &$params): string
	{
		$conditions = [];
		foreach ($words as $index => $word) {
			$name = ':w' . $index;
			$params[$name] = '%' . $this->escapeLike($word) . '%';
			$alternatives = [];
			foreach ($columns as $column) {
				$alternatives[] = 'LOWER(' . $column . ') LIKE ' . $name . self::LIKE_ESCAPE_CLAUSE;
			}
			$conditions[] = '(' . implode(' OR ', $alternatives) . ')';
		}
		return implode(' AND ', $conditions);
	}

	/**
	 * Searches posts.
	 * @param string $query the query
	 * @param array<string, mixed> $options board (BEForumBoard|int), member_id, page, page_size
	 * @throws BEForumValidationException when the query is too short
	 * @return array{0: BEForumPost[], 1: BEForumPagination} the posts and the pagination
	 */
	public function searchPosts(string $query, array $options = []): array
	{
		$words = $this->parseQuery($query);
		$this->getDbConnection();
		$pageSize = (int) ($options['page_size'] ?? $this->getModule()->getItemsPerPage());
		$params = [];
		$conditions = [
			$this->inCondition('board_id', $this->scopeBoardIds($options)),
			'is_deleted = :notdeleted',
			'is_approved = :approved',
			$this->getModule()->getPosts()->publicThreadCondition(),
			$this->buildWordCondition($words, ['content'], $params),
		];
		$params[':notdeleted'] = false;
		$params[':approved'] = true;
		if (!empty($options['member_id'])) {
			$conditions[] = 'member_id = :member';
			$params[':member'] = (int) $options['member_id'];
		}
		$criteria = BEForumPost::criteria(implode(' AND ', $conditions), $params, ['created_at' => 'desc']);
		$criteria = $this->dySearchCriteria($criteria, $query, $words, self::SCOPE_POSTS, $options);
		$pagination = $this->paginate((int) ($options['page'] ?? 1), $pageSize, (int) BEForumPost::finder()->count($this->countCriteria($criteria)));
		$criteria->setLimit($pageSize);
		$criteria->setOffset($pagination->getOffset());
		return [BEForumPost::finder()->findAll($criteria), $pagination];
	}

	/**
	 * Searches thread titles.
	 * @param string $query the query
	 * @param array<string, mixed> $options board (BEForumBoard|int), page, page_size
	 * @throws BEForumValidationException when the query is too short
	 * @return array{0: BEForumThread[], 1: BEForumPagination} the threads and the pagination
	 */
	public function searchThreads(string $query, array $options = []): array
	{
		$words = $this->parseQuery($query);
		$this->getDbConnection();
		$pageSize = (int) ($options['page_size'] ?? $this->getModule()->getItemsPerPage());
		$params = [];
		$conditions = [
			$this->inCondition('board_id', $this->scopeBoardIds($options)),
			'is_deleted = :notdeleted',
			'is_approved = :approved',
			$this->buildWordCondition($words, ['title'], $params),
		];
		$params[':notdeleted'] = false;
		$params[':approved'] = true;
		$criteria = BEForumThread::criteria(implode(' AND ', $conditions), $params, ['last_post_at' => 'desc']);
		$criteria = $this->dySearchCriteria($criteria, $query, $words, self::SCOPE_THREADS, $options);
		$pagination = $this->paginate((int) ($options['page'] ?? 1), $pageSize, (int) BEForumThread::finder()->count($this->countCriteria($criteria)));
		$criteria->setLimit($pageSize);
		$criteria->setOffset($pagination->getOffset());
		return [BEForumThread::finder()->findAll($criteria), $pagination];
	}

	/**
	 * @param array<string, mixed> $options the search options
	 * @return int[] the board ids in scope: the requested board (and its sub boards) or every visible board
	 */
	protected function scopeBoardIds(array $options): array
	{
		$boards = $this->getModule()->getBoards();
		$visible = $boards->getVisibleBoardIds();
		if (empty($options['board'])) {
			return $visible;
		}
		$board = $options['board'] instanceof \Belisoful\Forum\Data\BEForumBoard ? $options['board'] : $boards->findBoard((int) $options['board']);
		if ($board === null) {
			return $visible;
		}
		return array_values(array_intersect($boards->getDescendantIds($board), $visible));
	}

	/**
	 * Highlights the search words in an excerpt.
	 * @param string $text plain text
	 * @param string[] $words the words
	 * @param string $tag the wrapping HTML tag
	 * @return string HTML with escaped text and highlighted words
	 */
	public static function highlight(string $text, array $words, string $tag = 'mark'): string
	{
		$html = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		foreach ($words as $word) {
			if ($word === '') {
				continue;
			}
			$pattern = '/' . preg_quote(htmlspecialchars($word, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/') . '/iu';
			$html = preg_replace($pattern, '<' . $tag . '>$0</' . $tag . '>', $html) ?? $html;
		}
		return $html;
	}
}
