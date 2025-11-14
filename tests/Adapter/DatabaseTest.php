<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Adapter;

use Phalcon\Session\Adapter\AbstractAdapter;
use PHPUnit\Framework\TestCase;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Incubator\Session\Adapter\Database;

final class DatabaseTest extends TestCase
{
    private Sqlite $connection;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbFile = sys_get_temp_dir() . '/test_' . uniqid() . '.sqlite';
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }

        $this->connection = new Sqlite([
            'dbname' => $this->dbFile,
        ]);

        $tableName = Database::DEFAULT_TABLE_NAME;
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `$tableName` (
    `session_id` VARCHAR(35) NOT NULL,
    `data` text NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`session_id`)
);
SQL;

        $this->connection->execute($sql);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }

        parent::tearDown();
    }

    public function testImplementation(): void
    {
        $class = $this->createMock(Database::class);

        $this->assertInstanceOf(AbstractAdapter::class, $class);
    }

    public function testSetCustomColumn(): void
    {
        $class = new Database($this->connection, Database::DEFAULT_TABLE_NAME, [
            'session_id' => 'session_id',
        ]);

        $this->assertTrue($class->open(sys_get_temp_dir(), 'session-name'));
    }

    public function testOpen(): void
    {
        $class = new Database($this->connection);

        $this->assertTrue($class->open(sys_get_temp_dir(), 'session-name'));
    }

    public function testClose(): void
    {
        $class = new Database($this->connection);

        $this->assertTrue($class->close());
    }

    public function testReadEmpty(): void
    {
        $class = new Database($this->connection);

        $this->assertSame('', $class->read('un-existed'));
    }

    public function testWriteNotOpen(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);

        $this->assertFalse($class->write($sessionId, 'data'));
    }

    public function testWriteNew(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);
        $class->open(sys_get_temp_dir(), $sessionId);

        $this->assertTrue($class->write($sessionId, 'data'));
    }

    public function testWriteUpdate(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $oldData = 'data';
        $newData = 'new-data';

        $class = new Database($this->connection);
        $class->open(sys_get_temp_dir(), $sessionId);

        $this->assertTrue($class->write($sessionId, $oldData));
        $this->assertSame($oldData, $class->read($sessionId));
        $this->assertTrue($class->write($sessionId, $newData));
        $this->assertSame($newData, $class->read($sessionId));
    }

    public function testDestroyNotStarted(): void
    {
        $class = new Database($this->connection);

        $this->assertTrue($class->destroy('destroy'));
    }

    public function testDestroyStarted(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);
        $class->open(sys_get_temp_dir(), $sessionId);
        $class->write($sessionId, 'data');

        $this->assertTrue($class->destroy('destroy'));
    }

    public function testGc(): void
    {
        $class = new Database($this->connection);

        $this->assertTrue($class->gc(0));
    }

    public function testLazyWriteEnabled(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);
        $class->open(sys_get_temp_dir(), $sessionId);

        $date1 = $this->connection->fetchColumn(
            'SELECT modified_at FROM ' . Database::DEFAULT_TABLE_NAME . ' WHERE session_id = "' . $sessionId . '"'
        );

        ini_set('session.lazy_write', '1');

        sleep(1);
        $this->assertTrue($class->write($sessionId, 'data'));
        $date2 = $this->connection->fetchColumn(
            'SELECT modified_at FROM ' . Database::DEFAULT_TABLE_NAME . ' WHERE session_id = "' . $sessionId . '"'
        );
        $this->assertNotSame($date1, $date2);

        sleep(1);
        $this->assertTrue($class->write($sessionId, 'data'));
        $date3 = $this->connection->fetchColumn(
            'SELECT modified_at FROM ' . Database::DEFAULT_TABLE_NAME . ' WHERE session_id = "' . $sessionId . '"'
        );

        $this->assertSame($date2, $date3);
    }

    public function testLazyWriteDisabled(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);
        $class->open(sys_get_temp_dir(), $sessionId);

        $date1 = $this->connection->fetchColumn(
            'SELECT modified_at FROM ' . Database::DEFAULT_TABLE_NAME . ' WHERE session_id = "' . $sessionId . '"'
        );

        ini_set('session.lazy_write', '0');

        sleep(1);
        $this->assertTrue($class->write($sessionId, 'data'));
        $date2 = $this->connection->fetchColumn(
            'SELECT modified_at FROM ' . Database::DEFAULT_TABLE_NAME . ' WHERE session_id = "' . $sessionId . '"'
        );
        $this->assertNotSame($date1, $date2);

        sleep(1);
        $this->assertTrue($class->write($sessionId, 'data'));
        $date3 = $this->connection->fetchColumn(
            'SELECT modified_at FROM ' . Database::DEFAULT_TABLE_NAME . ' WHERE session_id = "' . $sessionId . '"'
        );
        $this->assertNotSame($date2, $date3);
    }
}
