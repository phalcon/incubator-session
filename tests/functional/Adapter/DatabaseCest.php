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

namespace Phalcon\Incubator\Session\Tests\Functional\Adapter;

use FunctionalTester;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Incubator\Session\Adapter\Database;

final class DatabaseCest
{
    private $connection;

    public function __construct()
    {
        $dbFile = codecept_output_dir('test.sqlite');
        if (file_exists($dbFile)) {
            unlink($dbFile);
        }

        $this->connection = new Sqlite([
            'dbname' => $dbFile,
        ]);

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `session_data` (
    `session_id` VARCHAR(35) NOT NULL,
    `data` text NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`session_id`)
);
SQL;

        $this->connection->execute($sql);
    }

    public function setCustomColumn(FunctionalTester $I): void
    {
        $class = new Database($this->connection, Database::DEFAULT_TABLE_NAME, [
            'session_id' => 'session_id',
        ]);

        $I->assertTrue($class->open(codecept_output_dir(), 'session-name'));
    }

    public function open(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertTrue($class->open(codecept_output_dir(), 'session-name'));
    }

    public function close(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertTrue($class->close());
    }

    public function readEmpty(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertSame('', $class->read('un-existed'));
    }

    public function writeNotOpen(FunctionalTester $I): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);

        $I->assertFalse($class->write($sessionId, 'data'));
    }

    public function writeNew(FunctionalTester $I): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);
        $class->open(codecept_output_dir(), $sessionId);

        $I->assertTrue($class->write($sessionId, 'data'));
    }

    public function writeUpdate(FunctionalTester $I): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $oldData = 'data';
        $newData = 'new-data';

        $class = new Database($this->connection);
        $class->open(codecept_output_dir(), $sessionId);

        $I->assertTrue($class->write($sessionId, $oldData));
        $I->assertSame($oldData, $class->read($sessionId));
        $I->assertTrue($class->write($sessionId, $newData));
        $I->assertSame($newData, $class->read($sessionId));
    }

    public function destroyNotStarted(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertTrue($class->destroy('destroy'));
    }

    public function destroyStarted(FunctionalTester $I): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);
        $class->open(codecept_output_dir(), $sessionId);
        $class->write($sessionId, 'data');

        $I->assertTrue($class->destroy('destroy'));
    }

    public function gc(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertTrue($class->gc(0));
    }
}
