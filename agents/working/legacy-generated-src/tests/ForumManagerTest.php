<?php

use PHPUnit\Framework\TestCase;
use PradoComposerExtension\BEForumManager;
use PradoComposerExtension\Forum\ActiveRecord\UserRecord;
use PradoComposerExtension\Forum\Category;
use PradoComposerExtension\Forum\ForumEntity;
use PradoComposerExtension\Forum\Thread;
use PradoComposerExtension\Forum\Post;
use PradoComposerExtension\Forum\Helper;

class ForumManagerTest extends TestCase
{
    public function testBEForumManagerInitialization()
    {
        $forumManager = new BEForumManager();
        $this->assertInstanceOf('PradoComposerExtension\BEForumManager', $forumManager);
    }
    
    public function testUserRecordClass()
    {
        $user = new UserRecord();
        $this->assertInstanceOf('PradoComposerExtension\Forum\ActiveRecord\UserRecord', $user);
        
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPostsCount(5);
        $user->setReputation(100);
        
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals(5, $user->getPostsCount());
        $this->assertEquals(100, $user->getReputation());
    }
    
    public function testCategoryClass()
    {
        $category = new Category();
        $this->assertInstanceOf('PradoComposerExtension\Forum\Category', $category);
        
        $category->setName('General Discussion');
        $category->setDescription('A place for general discussion');
        $category->setSortOrder(1);
        $category->setIsActive(true);
        $category->setForumCount(5);
        
        $this->assertEquals('General Discussion', $category->getName());
        $this->assertEquals('A place for general discussion', $category->getDescription());
        $this->assertEquals(1, $category->getSortOrder());
        $this->assertTrue($category->getIsActive());
        $this->assertEquals(5, $category->getForumCount());
    }
    
    public function testForumEntityClass()
    {
        $forum = new ForumEntity();
        $this->assertInstanceOf('PradoComposerExtension\Forum\ForumEntity', $forum);
        
        $forum->setName('Test Forum');
        $forum->setDescription('A test forum for development');
        $forum->setSortOrder(1);
        $forum->setIsActive(true);
        $forum->setPostCount(100);
        $forum->setThreadCount(10);
        
        $this->assertEquals('Test Forum', $forum->getName());
        $this->assertEquals('A test forum for development', $forum->getDescription());
        $this->assertEquals(1, $forum->getSortOrder());
        $this->assertTrue($forum->getIsActive());
        $this->assertEquals(100, $forum->getPostCount());
        $this->assertEquals(10, $forum->getThreadCount());
    }
    
    public function testThreadClass()
    {
        $thread = new Thread();
        $this->assertInstanceOf('PradoComposerExtension\Forum\Thread', $thread);
        
        $thread->setForumId(1);
        $thread->setUserId(2);
        $thread->setTitle('Test Thread');
        $thread->setIsSticky(true);
        $thread->setIsLocked(false);
        $thread->setViewCount(25);
        $thread->setReplyCount(5);
        
        $this->assertEquals(1, $thread->getForumId());
        $this->assertEquals(2, $thread->getUserId());
        $this->assertEquals('Test Thread', $thread->getTitle());
        $this->assertTrue($thread->getIsSticky());
        $this->assertFalse($thread->getIsLocked());
        $this->assertEquals(25, $thread->getViewCount());
        $this->assertEquals(5, $thread->getReplyCount());
    }
    
    public function testPostClass()
    {
        $post = new Post();
        $this->assertInstanceOf('PradoComposerExtension\Forum\Post', $post);
        
        $post->setThreadId(1);
        $post->setUserId(2);
        $post->setContent('This is a test post');
        $post->setIsFirstPost(true);
        $post->setIsEdited(false);
        $post->setEditReason('');
        
        $this->assertEquals(1, $post->getThreadId());
        $this->assertEquals(2, $post->getUserId());
        $this->assertEquals('This is a test post', $post->getContent());
        $this->assertTrue($post->getIsFirstPost());
        $this->assertFalse($post->getIsEdited());
        $this->assertEquals('', $post->getEditReason());
    }
    
    public function testHelperClass()
    {
        $this->assertInstanceOf('PradoComposerExtension\Forum\Helper', new Helper());
        
        // Test text truncation
        $longText = 'This is a very long text that should be truncated when it exceeds the limit';
        $truncated = Helper::truncateText($longText, 20);
        $this->assertStringContainsString('..', $truncated);
        
        // Test BBCode processing
        $bbcode = '[b]bold[/b] [i]italic[/i] [url=http://example.com]link[/url]';
        $formatted = Helper::formatPostContent($bbcode);
        $this->assertStringContainsString('<strong>', $formatted);
        $this->assertStringContainsString('<em>', $formatted);
        $this->assertStringContainsString('<a href="http://example.com">', $formatted);
    }
}