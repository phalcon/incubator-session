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

use Phalcon\Db\Adapter\AbstractAdapter as DbAbstractAdapter;
use Phalcon\Db\Column;
use Phalcon\Db\Enum;
use Phalcon\Session\Adapter\AbstractAdapter;

/**
 * Database adapter for Phalcon\Session
 */
class Database extends AbstractAdapter
{
    public const DEFAULT_TABLE_NAME = 'session_data';

    /**
     * Database connection
     */
    protected DbAbstractAdapter $connection;

    /**
     * Database Table name
     */
    protected string $tableName;

    /**
     * Check if session started
     */
    protected bool $started = false;

    protected array $defaultColumns = [
        'session_id',
        'data',
        'created_at',
        'modified_at',
    ];

    /**
     * Final columns
     *
     * After applying user custom columns from $columns
     */
    protected array $columns = [];

    public function __construct(
        DbAbstractAdapter $connection,
        string $tableName = self::DEFAULT_TABLE_NAME,
        array $columns = []
    ) {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->prepareColumns($columns);

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
     * @param mixed $savePath
     * @param mixed $sessionName
     * @return bool
     */
    public function open($savePath, $sessionName): bool
    {
        $this->started = true;

        return true;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        $this->started = false;

        return true;
    }

    /**
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId): string
    {
        if (!$this->started) {
            return '';
        }

        $maxLifetime = (int)ini_get('session.gc_maxlifetime');
        $row = $this->connection->fetchOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = ? AND COALESCE(%s, %s) >= ?',
                $this->connection->escapeIdentifier($this->columns['data']),
                $this->getTableName(),
                $this->connection->escapeIdentifier($this->columns['session_id']),
                $this->connection->escapeIdentifier($this->columns['modified_at']),
                $this->connection->escapeIdentifier($this->columns['created_at'])
            ),
            Enum::FETCH_NUM,
            [$sessionId, date('Y-m-d H:i:s', strtotime('-' . $maxLifetime . ' seconds'))],
            [Column::BIND_PARAM_STR, Column::BIND_PARAM_STR]
        );

        if (empty($row)) {
            return '';
        }

        return $row[0];
    }

    /**
     * @param string $sessionId
     * @param string $data
     * @return bool
     */
    public function write($sessionId, $data): bool
    {
        if (!$this->started) {
            return false;
        }

        /**
         * Locking reads are only possible when autocommit is disabled,
         * either by beginning transaction with START TRANSACTION or by
         * setting autocommit to 0.
         */

        $this->connection->begin();

        $row = $this->connection->fetchOne(
            sprintf(
                $this->connection->forUpdate('SELECT %s AS data FROM %s WHERE %s = ?'),
                $this->connection->escapeIdentifier($this->columns['data']),
                $this->getTableName(),
                $this->connection->escapeIdentifier($this->columns['session_id'])
            ),
            Enum::FETCH_ASSOC,
            [$sessionId]
        );

        if (!empty($row)) {
            /**
             * When set to 1, means that session data is only rewritten if it changes.
             * Defaults to 1, enabled.
             *
             * @see https://www.php.net/manual/en/session.configuration.php#ini.session.lazy-write
             */
            $lazyWrite = (bool)ini_get('session.lazy_write');
            if ($lazyWrite === true && $row['data'] === $data) {
                $this->connection->rollback();

                return true;
            }

            $update = $this->connection->execute(
                sprintf(
                    'UPDATE %s SET %s = ?, %s = ? WHERE %s = ?',
                    $this->getTableName(),
                    $this->connection->escapeIdentifier($this->columns['data']),
                    $this->connection->escapeIdentifier($this->columns['modified_at']),
                    $this->connection->escapeIdentifier($this->columns['session_id'])
                ),
                [$data, date('Y-m-d H:i:s'), $sessionId]
            );

            $this->connection->commit();

            return $update;
        }

        $insert = $this->connection->execute(
            sprintf(
                'INSERT INTO %s (%s, %s) VALUES (?, ?)',
                $this->getTableName(),
                $this->connection->escapeIdentifier($this->columns['session_id']),
                $this->connection->escapeIdentifier($this->columns['data'])
            ),
            [$sessionId, $data]
        );

        $this->connection->commit();

        return $insert;
    }

    /**
     * @param string $sessionId
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        if (!$this->started) {
            return true;
        }

        $this->started = false;

        return $this->connection->execute(
            sprintf(
                'DELETE FROM %s WHERE %s = ?',
                $this->getTableName(),
                $this->connection->escapeIdentifier($this->columns['session_id'])
            ),
            [$sessionId]
        );
    }

    #[\ReturnTypeWillChange]
    public function gc($maxLifeTime): bool
    {
        return $this->connection->execute(
            sprintf(
                'DELETE FROM %s WHERE COALESCE(%s, %s) < ?',
                $this->getTableName(),
                $this->connection->escapeIdentifier($this->columns['modified_at']),
                $this->connection->escapeIdentifier($this->columns['created_at'])
            ),
            [date('Y-m-d H:i:s', strtotime('-' . $maxLifeTime . ' seconds'))]
        );
    }

    /**
     * Get escaped table name
     */
    protected function getTableName(): string
    {
        return $this->connection->escapeIdentifier($this->tableName);
    }

    /**
     * Pick default columns and merge with custom (if any passed)
     */
    protected function prepareColumns(array $columns): void
    {
        foreach ($this->defaultColumns as $defaultColumn) {
            if (isset($columns[$defaultColumn])) {
                $this->columns[$defaultColumn] = $columns[$defaultColumn];
            } else {
                $this->columns[$defaultColumn] = $defaultColumn;
            }
        }
    }
}
