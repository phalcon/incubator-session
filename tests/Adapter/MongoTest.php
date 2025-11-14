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
use MongoDB\Client;
use Phalcon\Incubator\Session\Adapter\Mongo;

final class MongoTest extends TestCase
{
    private Mongo $mongo;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client('mongodb://127.0.0.1:27017');
        $this->mongo = new Mongo($client->test->session_data);
    }

    public function testImplementation(): void
    {
        $class = $this->createMock(Mongo::class);

        $this->assertInstanceOf(AbstractAdapter::class, $class);
    }

    public function testOpen(): void
    {
        $this->assertTrue($this->mongo->open('', 'session-name'));
    }

    public function testReadEmpty(): void
    {
        $this->assertSame('', $this->mongo->read('session-id'));
    }

    public function testWriteNew(): void
    {
        $this->assertTrue($this->mongo->write('session-id', 'data'));
    }

    public function testWriteDuplicate(): void
    {
        $sessionId = bin2hex(random_bytes(32));
        $sessionData = 'value';

        $this->mongo->open(sys_get_temp_dir(), $sessionId);

        $this->assertTrue($this->mongo->write($sessionId, $sessionData));
        // Assert that data is identical and just returns true
        $this->assertTrue($this->mongo->write($sessionId, $sessionData));
        $this->assertSame($sessionData, $this->mongo->read($sessionId));
    }

    public function testWriteUpdate(): void
    {
        $sessionid = bin2hex(random_bytes(32));
        $sessionData = 'value';
        $newSessionData = 'new-value';

        $this->mongo->open(sys_get_temp_dir(), $sessionid);

        $this->assertTrue($this->mongo->write($sessionid, $sessionData));
        $this->assertSame($sessionData, $this->mongo->read($sessionid));

        $this->assertTrue($this->mongo->write($sessionid, $newSessionData));
        $this->assertSame($newSessionData, $this->mongo->read($sessionid));
    }
}
