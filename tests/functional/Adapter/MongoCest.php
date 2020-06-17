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

use MongoDB\Client;
use Phalcon\Incubator\Session\Adapter\Mongo;

final class MongoCest
{
    public function testWrite(\FunctionalTester $I): void
    {
        $client = new Client('mongodb+srv://root:example@mongodb/test?retryWrites=true&w=majority');

        $mongo = new Mongo($client->test->session_data);

        $I->assertTrue($mongo->write('session-id', 'data'));
    }
}
