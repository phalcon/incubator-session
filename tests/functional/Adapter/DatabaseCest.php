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
        $this->connection = new Sqlite([
            'dbname' => codecept_output_dir('test.sqlite'),
        ]);
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

        $I->assertTrue($class->read('un-existed'));
    }

    public function write(FunctionalTester $I): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $class = new Database($this->connection);

        $I->assertTrue($class->write($sessionId, 'data'));
    }

    public function destroy(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertIsBool($class->destroy('destroy'));
    }

    public function gc(FunctionalTester $I): void
    {
        $class = new Database($this->connection);

        $I->assertIsBool($class->gc(0));
    }
}
