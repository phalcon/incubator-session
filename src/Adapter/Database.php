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
use Phalcon\Db\Enum;
use Phalcon\Session\Adapter\AbstractAdapter;
use Phalcon\Db\Column;
use Phalcon\Session\Exception;

/**
 * Database adapter for Phalcon\Session
 */
class Database extends AbstractAdapter
{
    /**
     * @var DbAbstractAdapter
     */
    protected $connection;

    /**
     * Check if session started
     *
     * @var bool
     */
    protected $started = false;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = null)
    {
        if (!isset($options['db']) || !$options['db'] instanceof DbAbstractAdapter) {
            throw new Exception(
                'Parameter "db" is required and it must be an instance of Phalcon\DbAdapter\AdapterInterface'
            );
        }

        $this->connection = $options['db'];
        unset($options['db']);

        if (empty($options['table']) || !is_string($options['table'])) {
            throw new Exception(
                "Parameter 'table' is required and it must be a non empty string"
            );
        }

        /**
         * TODO: rework
         */
        $columns = ['session_id', 'data', 'created_at', 'modified_at'];
        foreach ($columns as $column) {
            $oColumn = "column_$column";
            if (empty($options[$oColumn]) || !is_string($options[$oColumn])) {
                $options[$oColumn] = $column;
            }
        }

        $this->options = $options;
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
     * @param $savePath
     * @param $sessionName
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
        $maxLifetime = (int)ini_get('session.gc_maxlifetime');

        if (!$this->started) {
            return '';
        }

        $row = $this->connection->fetchOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = ? AND ADDTIME(COALESCE(%s, %s), %d) >= ?',
                $this->connection->escapeIdentifier($this->options['column_data']),
                $this->connection->escapeIdentifier($this->options['table']),
                $this->connection->escapeIdentifier($this->options['column_session_id']),
                $this->connection->escapeIdentifier($this->options['column_modified_at']),
                $this->connection->escapeIdentifier($this->options['column_created_at']),
                $maxLifetime
            ),
            Enum::FETCH_NUM,
            [$sessionId, date('Y-m-d H:i:s')],
            [Column::BIND_PARAM_STR, Column::BIND_PARAM_INT]
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
        $row = $this->connection->fetchOne(
            sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s = ?',
                $this->connection->escapeIdentifier($this->options['table']),
                $this->connection->escapeIdentifier($this->options['column_session_id'])
            ),
            Enum::FETCH_NUM,
            [$sessionId]
        );

        if ($row[0] > 0) {
            return $this->connection->execute(
                sprintf(
                    'UPDATE %s SET %s = ?, %s = ? WHERE %s = ?',
                    $this->connection->escapeIdentifier($this->options['table']),
                    $this->connection->escapeIdentifier($this->options['column_data']),
                    $this->connection->escapeIdentifier($this->options['column_modified_at']),
                    $this->connection->escapeIdentifier($this->options['column_session_id'])
                ),
                [$data, date('Y-m-d H:i:s'), $sessionId]
            );
        }

        if (!$this->started) {
            return false;
        }

        return $this->connection->execute(
            sprintf(
                'INSERT INTO %s (%s, %s) VALUES (?, ?)',
                $this->connection->escapeIdentifier($this->options['table']),
                $this->connection->escapeIdentifier($this->options['column_session_id']),
                $this->connection->escapeIdentifier($this->options['column_data'])
            ),
            [$sessionId, $data]
        );
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
                $this->connection->escapeIdentifier($this->options['table']),
                $this->connection->escapeIdentifier($this->options['column_session_id'])
            ),
            [$sessionId]
        );
    }

    /**
     * @param int $maxLifeTime
     * @return boolean
     */
    public function gc($maxLifeTime): bool
    {
        return $this->connection->execute(
            sprintf(
                'DELETE FROM %s WHERE ADDTIME(COALESCE(%s, %s), %d) < ?',
                $this->connection->escapeIdentifier($this->options['table']),
                $this->connection->escapeIdentifier($this->options['column_modified_at']),
                $this->connection->escapeIdentifier($this->options['column_created_at']),
                $maxLifeTime
            ),
            [date('Y-m-d H:i:s')]
        );
    }
}
