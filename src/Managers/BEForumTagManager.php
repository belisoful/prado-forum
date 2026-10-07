<?php

/**
 * BEForumTagManager class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 */

namespace Belisoful\Forum\Managers;

use Belisoful\Forum\Data\BEForumTag;
use Belisoful\Forum\Data\BEForumThread;
use Belisoful\Forum\Data\BEForumThreadTag;
use Belisoful\Forum\Exceptions\BEForumValidationException;
use Belisoful\Forum\Security\BEForumPermissions;
use Belisoful\Forum\Util\BEForumSlug;

/**
 * BEForumTagManager class.
 *
 * BEForumTagManager normalises tag names, assigns tags to threads, keeps the
 * per tag thread counters and serves tag clouds.
 *
 * ```php
 * $forum->getTags()->setThreadTags($thread, ['PHP', 'prado framework']);
 * $cloud = $forum->getTags()->getPopularTags(30);
 * ```
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 0.1.0
 */
class BEForumTagManager extends BEForumManager
{
	/** The maximum tag name length */
	public const MAX_LENGTH = 64;

	/**
	 * Normalises a tag name: trimmed, single spaced, at most {@see MAX_LENGTH} characters.
	 * @param string $name the tag name
	 * @return string the normalised name, empty when nothing remains
	 */
	public function normalizeName(string $name): string
	{
		$name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
		$name = ltrim($name, '#');
		return mb_substr($name, 0, self::MAX_LENGTH);
	}

	/**
	 * Splits a comma separated tag list into normalised names.
	 * @param null|string $list the list, e.g. `php, prado`
	 * @return string[] the unique names
	 */
	public function parseList(?string $list): array
	{
		$names = [];
		foreach (preg_split('/[,\n]+/', (string) $list) ?: [] as $name) {
			$name = $this->normalizeName($name);
			if ($name !== '') {
				$names[BEForumSlug::create($name, self::MAX_LENGTH)] ??= $name;
			}
		}
		return array_values($names);
	}

	/**
	 * @param string $slug the tag slug (or name)
	 * @return null|BEForumTag the tag
	 */
	public function findBySlug(string $slug): ?BEForumTag
	{
		$this->getDbConnection();
		$tag = BEForumTag::finder()->find('slug = ?', [BEForumSlug::create($slug, self::MAX_LENGTH)]);
		return $tag instanceof BEForumTag ? $tag : null;
	}

	/**
	 * Finds or creates a tag.
	 * @param string $name the tag name
	 * @throws BEForumValidationException when the name is empty
	 * @return BEForumTag the tag
	 */
	public function ensureTag(string $name): BEForumTag
	{
		$name = $this->normalizeName($name);
		if ($name === '') {
			throw new BEForumValidationException('tags', 'forum_tag_name_required');
		}
		$tag = $this->findBySlug($name);
		if ($tag === null) {
			$tag = new BEForumTag();
			$tag->slug = BEForumSlug::create($name, self::MAX_LENGTH);
			$tag->name = $name;
			$tag->save();
		}
		return $tag;
	}

	/**
	 * Replaces the tags of a thread.
	 * @param BEForumThread $thread the thread
	 * @param string[] $names the tag names (or one comma separated string)
	 * @param bool $authorize whether to check the thread edit permission
	 * @throws BEForumValidationException when there are too many tags
	 * @return BEForumTag[] the tags of the thread after the change
	 */
	public function setThreadTags(BEForumThread $thread, array $names, bool $authorize = true): array
	{
		if ($authorize) {
			$this->authorize(BEForumPermissions::THREAD_EDIT, $this->extraFor((int) $thread->board_id, $thread->member_id ? $this->getModule()->getMembers()->findById((int) $thread->member_id)?->username : null));
		}
		$this->getDbConnection();
		$wanted = [];
		foreach ($names as $name) {
			foreach ($this->parseList((string) $name) as $parsed) {
				$wanted[BEForumSlug::create($parsed, self::MAX_LENGTH)] = $parsed;
			}
		}
		$max = $this->getModule()->getMaxTagsPerThread();
		if ($max > 0 && count($wanted) > $max) {
			throw new BEForumValidationException('tags', 'forum_too_many_tags', $max);
		}
		$current = [];
		foreach (BEForumThreadTag::finder()->findAll('thread_id = ?', [$thread->getId()]) as $assignment) {
			$current[(int) $assignment->tag_id] = $assignment;
		}
		$tags = [];
		$keep = [];
		foreach ($wanted as $name) {
			$tag = $this->ensureTag($name);
			$tags[] = $tag;
			$keep[$tag->getId()] = true;
			if (!isset($current[$tag->getId()])) {
				$assignment = new BEForumThreadTag();
				$assignment->thread_id = $thread->getId();
				$assignment->tag_id = $tag->getId();
				$assignment->save();
				BEForumTag::execute('UPDATE {table} SET thread_count = thread_count + 1 WHERE id = :id', ['id' => $tag->getId()]);
			}
		}
		foreach ($current as $tagId => $assignment) {
			if (!isset($keep[$tagId])) {
				$assignment->delete();
				BEForumTag::execute('UPDATE {table} SET thread_count = thread_count - 1 WHERE id = :id AND thread_count > 0', ['id' => $tagId]);
			}
		}
		$this->flushRequestCache();
		return $tags;
	}

	/**
	 * @param BEForumThread $thread the thread
	 * @return BEForumTag[] the tags of the thread
	 */
	public function getThreadTags(BEForumThread $thread): array
	{
		return $this->getTagsForThreads([(int) $thread->getId()])[(int) $thread->getId()] ?? [];
	}

	/**
	 * Loads the tags of several threads in two queries.
	 * @param int[] $threadIds the thread ids
	 * @return array<int, BEForumTag[]> the tags keyed by thread id
	 */
	public function getTagsForThreads(array $threadIds): array
	{
		$threadIds = array_values(array_unique(array_filter(array_map('intval', $threadIds))));
		if (!$threadIds) {
			return [];
		}
		$this->getDbConnection();
		$assignments = BEForumThreadTag::finder()->findAll($this->inCondition('thread_id', $threadIds));
		$tagIds = array_map(fn (BEForumThreadTag $assignment) => (int) $assignment->tag_id, $assignments);
		$tags = $tagIds ? $this->indexById(BEForumTag::finder()->findAll(BEForumTag::criteria($this->inCondition('id', $tagIds), [], ['name' => 'asc']))) : [];
		$result = [];
		foreach ($assignments as $assignment) {
			$tag = $tags[(int) $assignment->tag_id] ?? null;
			if ($tag !== null) {
				$result[(int) $assignment->thread_id][] = $tag;
			}
		}
		return $result;
	}

	/**
	 * @param BEForumTag $tag the tag
	 * @return int[] the ids of the threads carrying the tag
	 */
	public function getThreadIdsForTag(BEForumTag $tag): array
	{
		$this->getDbConnection();
		return array_map(fn (BEForumThreadTag $assignment) => (int) $assignment->thread_id, BEForumThreadTag::finder()->findAll('tag_id = ?', [$tag->getId()]));
	}

	/**
	 * @param int $limit the maximum number of tags
	 * @return BEForumTag[] the most used tags, by count then name
	 */
	public function getPopularTags(int $limit = 30): array
	{
		$this->getDbConnection();
		return BEForumTag::finder()->findAll(BEForumTag::criteria('thread_count > 0', [], ['thread_count' => 'desc', 'name' => 'asc'], max(1, $limit)));
	}

	/**
	 * @param string $prefix a name prefix
	 * @param int $limit the maximum number of tags
	 * @return BEForumTag[] the matching tags for auto completion
	 */
	public function suggest(string $prefix, int $limit = 10): array
	{
		$prefix = $this->normalizeName($prefix);
		if ($prefix === '') {
			return [];
		}
		$this->getDbConnection();
		return BEForumTag::finder()->findAll(BEForumTag::criteria('LOWER(name) LIKE ?' . self::LIKE_ESCAPE_CLAUSE, [mb_strtolower($this->escapeLike($prefix)) . '%'], ['thread_count' => 'desc', 'name' => 'asc'], max(1, $limit)));
	}

	/**
	 * Deletes tags no thread uses.
	 * @return int the number of tags removed
	 */
	public function removeUnused(): int
	{
		$this->getDbConnection();
		return (int) BEForumTag::finder()->deleteAll('thread_count <= 0');
	}

	/**
	 * Recomputes the thread counters of every tag.
	 * @return int the number of tags recounted
	 */
	public function recountAll(): int
	{
		$this->getDbConnection();
		$count = 0;
		foreach (BEForumTag::finder()->findAll() as $tag) {
			BEForumTag::execute('UPDATE {table} SET thread_count = :count WHERE id = :id', ['count' => BEForumThreadTag::countWhere('tag_id = ?', [$tag->getId()]), 'id' => $tag->getId()]);
			$count++;
		}
		return $count;
	}
}
