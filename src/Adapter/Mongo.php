<?php

/**
 * This file is part of the Phalcon Migrations.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Incubator\Session\Adapter;

use DateInterval;
use DateTime;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Phalcon\Session\Adapter\AbstractAdapter;

/**
 * Mongo adapter for Phalcon\Session
 */
class Mongo extends AbstractAdapter
{
    protected Collection $collection;

    /**
     * Current session data
     */
    protected ?string $data = null;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;

        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc'],
        );
    }

    #[\Override]
    public function open($path, $name): bool
    {
        return true;
    }

    #[\Override]
    public function close(): bool
    {
        return true;
    }

    #[\Override]
    public function read($id): string
    {
        $sessionData = $this->collection->findOne([
            '_id' => $id,
        ]);

        if (!isset($sessionData['data'])) {
            return '';
        }

        $this->data = $sessionData['data'];

        return $sessionData['data'];
    }

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    #[\Override]
    public function write($id, $data): bool
    {
        if ($this->data === $data) {
            return true;
        }

        $countDocuments = $this->collection->countDocuments(['_id' => $id]);
        if ($countDocuments === 0) {
            $insertResult = $this->collection->insertOne([
                '_id' => $id,
                'modified' => null,
                'data' => $data,
            ]);

            return $insertResult->getInsertedCount() > 0;
        }

        $updateResult = $this->collection->updateOne(
            ['_id' => $id],
            ['$set' => ['modified' => new UTCDateTime(), 'data' => $data]],
        );

        return $updateResult->getModifiedCount() > 0;
    }

    /**
     * @param string $id
     */
    #[\Override]
    public function destroy($id): bool
    {
        $this->data = null;
        $deleteResult = $this->collection->deleteOne(['_id' => $id]);

        return $deleteResult->getDeletedCount() > 0;
    }

    /**
     * Garbage Collector
     *
     * @param int $max_lifetime
     * @return int|false
     */
    #[\Override]
    public function gc(int $max_lifetime): int|false
    {
        $date = new DateTime();
        $date->sub(new DateInterval('PT' . $max_lifetime . 'S'));
        $minAgeMongo = new UTCDateTime($date->getTimestamp());

        $deleteResult = $this->collection->deleteMany([
            'modified' => [
                '$lte' => $minAgeMongo,
            ],
        ]);

        return $deleteResult->getDeletedCount();
    }
}
