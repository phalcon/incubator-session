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
     * @param mixed $path
     * @param mixed $name
     * @return bool
     */
    #[\Override]
    public function open($path, $name): bool
    {
        $this->started = true;

        return true;
    }

    #[\Override]
    public function close(): bool
    {
        $this->started = false;

        return true;
    }

    /**
     * @param string $id
     * @return string
     */
    #[\Override]
    public function read($id): string
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
            [$id, date('Y-m-d H:i:s', strtotime('-' . $maxLifetime . ' seconds'))],
            [Column::BIND_PARAM_STR, Column::BIND_PARAM_STR]
        );

        if (empty($row)) {
            return '';
        }

        return $row[0];
    }

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    #[\Override]
    public function write($id, $data): bool
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
            [$id]
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
                [$data, date('Y-m-d H:i:s'), $id]
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
            [$id, $data]
        );

        $this->connection->commit();

        return $insert;
    }

    /**
     * @param string $id
     * @return bool
     */
    #[\Override]
    public function destroy($id): bool
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
            [$id]
        );
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
        $result = $this->connection->execute(
            sprintf(
                'DELETE FROM %s WHERE COALESCE(%s, %s) < ?',
                $this->getTableName(),
                $this->connection->escapeIdentifier($this->columns['modified_at']),
                $this->connection->escapeIdentifier($this->columns['created_at'])
            ),
            [date('Y-m-d H:i:s', strtotime('-' . $max_lifetime . ' seconds'))]
        );

        if ($result === false) {
            return false;
        }

        return $this->connection->affectedRows();
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
