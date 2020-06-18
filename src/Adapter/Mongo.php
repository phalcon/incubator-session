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
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * Current session data
     *
     * @var string
     */
    protected $data;

    /**
     * Class constructor.
     *
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;

        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
    }

    /**
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId): string
    {
        $sessionData = $this->collection->findOne([
            '_id' => $sessionId,
        ]);

        if (!isset($sessionData['data'])) {
            return '';
        }

        $this->data = $sessionData['data'];

        return $sessionData['data'];
    }

    /**
     * @param string $sessionId
     * @param string $sessionData
     * @return bool
     */
    public function write($sessionId, $sessionData): bool
    {
        if ($this->data === $sessionData) {
            return true;
        }

        $countDocuments = $this->collection->countDocuments(['_id' => $sessionId]);
        if ($countDocuments === 0) {
            $insertResult = $this->collection->insertOne([
                '_id' => $sessionId,
                'modified' => null,
                'data' => $sessionData,
            ]);

            return $insertResult->getInsertedCount() > 0;
        }

        $updateResult = $this->collection->updateOne(
            ['_id' => $sessionId],
            ['$set' => ['modified' => new UTCDateTime(), 'data' => $sessionData]]
        );

        return $updateResult->getModifiedCount() > 0;
    }

    /**
     * @param string $sessionId
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        $this->data = null;
        $deleteResult = $this->collection->deleteOne(['_id' => $sessionId]);

        return $deleteResult->getDeletedCount() > 0;
    }

    /**
     * @param mixed $maxLifetime
     * @return bool
     * @throws \Exception
     */
    public function gc($maxLifetime): bool
    {
        $date = new DateTime();
        $date->sub(new DateInterval('PT' . $maxLifetime . 'S'));
        $minAgeMongo = new UTCDateTime($date->getTimestamp());

        $deleteResult = $this->collection->deleteMany([
            'modified' => [
                '$lte' => $minAgeMongo,
            ],
        ]);

        return $deleteResult->getDeletedCount() > 0;
    }
}
