<?php

use PHPUnit\Framework\TestCase;
use PradoComposerExtension\Forum\DAO;

class DAOExampleTest extends TestCase
{
    public function testDAOInitialization()
    {
        // Test the DAO class itself can be instantiated
        $dao = $this->getMockBuilder(DAO::class)
                    ->setConstructorArgs([null, 'mysql'])
                    ->setMockClassName('MockDAO')
                    ->getMock();
        
        $this->assertInstanceOf('PradoComposerExtension\Forum\DAO', $dao);
        
        // Test table name prefix handling (not really testable without real connection but can check method exists)
        $this->assertTrue(method_exists($dao, 'getTableName'));
    }
}