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
use MongoDB\Client;
use Phalcon\Incubator\Session\Adapter\Mongo;
use Dotenv\Dotenv;

final class MongoCest
{
    private $mongoClient;

    public function __construct()
    {
        $dotenv = new Dotenv(__DIR__ . "/../../_ci/");
        $dotenv->load();

        $mongoHost = getenv('DATA_MONGO_HOST');
        $mongoPort = getenv('DATA_MONGO_PORT');
        $mongoUser = getenv('DATA_MONGO_USER');
        $mongoPass = getenv('DATA_MONGO_PASS');
        $this->mongoClient =  new Client("mongodb://$mongoUser:$mongoPass@$mongoHost:$mongoPort");
    }

    public function open(FunctionalTester $I): void
    {
        $mongo = new Mongo($this->mongoClient->test->session_data);
        $I->assertTrue($mongo->open('', 'session-name'));
    }

    public function testReadEmpty(FunctionalTester $I): void
    {
        $mongo = new Mongo($this->mongoClient->test->session_data);
        $I->assertSame('', $mongo->read('session-id'));
    }

    public function testWriteNew(FunctionalTester $I): void
    {
        $mongo = new Mongo($this->mongoClient->test->session_data);
        $I->assertTrue($mongo->write('session-id', 'data'));
    }

    public function testWriteDuplicate(FunctionalTester $I): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $sessionData = 'value';

        $mongo = new Mongo($this->mongoClient->test->session_data);
        $mongo->open(codecept_output_dir(), $sessionId);

        $I->assertTrue($mongo->write($sessionId, $sessionData));
        // Assert that data is identical and just returns true
        $I->assertTrue($mongo->write($sessionId, $sessionData));
        $I->assertSame($sessionData, $mongo->read($sessionId));
    }

    public function testWriteUpdate(FunctionalTester $I): void
    {
        $sessionid = bin2hex(random_bytes(32));
        $sessionData = 'value';
        $newSessionData = 'new-value';

        $mongo = new Mongo($this->mongoClient->test->session_data);
        $mongo->open(codecept_output_dir(), $sessionid);

        $I->assertTrue($mongo->write($sessionid, $sessionData));
        $I->assertSame($sessionData, $mongo->read($sessionid));

        $I->assertTrue($mongo->write($sessionid, $newSessionData));
        $I->assertSame($newSessionData, $mongo->read($sessionid));
    }
}
