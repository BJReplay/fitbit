<?php

declare(strict_types=1);

/**
 * Stores PHP sessions in MySQL.
 *
 * A dedicated database connection should be supplied so application code
 * can close its own connection without preventing PHP from writing the
 * session during request shutdown.
 */
final class MySqlSessionHandler implements SessionHandlerInterface
{
    private mysqli $connection;

    private ?string $lockedSessionId = null;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    public function open(
        string $path,
        string $name
    ): bool {
        return true;
    }

    public function close(): bool
    {
        /*
         * session_regenerate_id() can close and reopen the same handler
         * during one request. Release the session lock here, but leave the
         * dedicated connection open for the handler to reuse. PHP closes
         * the connection automatically when the request ends.
         */
        return $this->releaseLock();
    }

    public function read(string $id): string|false
    {
        if (!$this->isValidSessionId($id)) {
            error_log('PHP supplied an invalid session identifier.');

            return false;
        }

        if (!$this->acquireLock($id)) {
            return false;
        }

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'SELECT session_data
                   FROM sessions
                  WHERE session_id = ?
                  LIMIT 1'
            );

            mysqli_stmt_bind_param(
                $statement,
                's',
                $id
            );

            mysqli_stmt_execute($statement);

            $sessionData = null;

            mysqli_stmt_bind_result(
                $statement,
                $sessionData
            );

            $found = mysqli_stmt_fetch($statement);

            mysqli_stmt_close($statement);

            if ($found !== true) {
                return '';
            }

            if (!is_string($sessionData)) {
                error_log(
                    'The stored PHP session data is not a string.'
                );

                return false;
            }

            return $sessionData;
        } catch (mysqli_sql_exception $exception) {
            error_log(
                'Unable to read the PHP session from MySQL: ' .
                $exception->getMessage()
            );

            return false;
        }
    }

    public function write(
        string $id,
        string $data
    ): bool {
        if (!$this->isValidSessionId($id)) {
            error_log('PHP supplied an invalid session identifier.');

            return false;
        }

        if (
            $this->lockedSessionId !== $id &&
            !$this->acquireLock($id)
        ) {
            return false;
        }

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'INSERT INTO sessions
                    (
                        session_id,
                        session_data,
                        last_activity
                    )
                 VALUES
                    (
                        ?,
                        ?,
                        UTC_TIMESTAMP()
                    )
                 ON DUPLICATE KEY UPDATE
                    session_data = VALUES(session_data),
                    last_activity = UTC_TIMESTAMP()'
            );

            mysqli_stmt_bind_param(
                $statement,
                'ss',
                $id,
                $data
            );

            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            return $this->releaseLock();
        } catch (mysqli_sql_exception $exception) {
            error_log(
                'Unable to write the PHP session to MySQL: ' .
                $exception->getMessage()
            );

            $this->releaseLock();

            return false;
        }
    }

    public function destroy(string $id): bool
    {
        if (!$this->isValidSessionId($id)) {
            error_log('PHP supplied an invalid session identifier.');

            return false;
        }

        if (
            $this->lockedSessionId !== $id &&
            !$this->acquireLock($id)
        ) {
            return false;
        }

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'DELETE FROM sessions
                  WHERE session_id = ?'
            );

            mysqli_stmt_bind_param(
                $statement,
                's',
                $id
            );

            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            return $this->releaseLock();
        } catch (mysqli_sql_exception $exception) {
            error_log(
                'Unable to delete the PHP session from MySQL: ' .
                $exception->getMessage()
            );

            $this->releaseLock();

            return false;
        }
    }

    public function gc(int $maxLifetime): int|false
    {
        if ($maxLifetime < 1) {
            error_log(
                'PHP supplied an invalid session garbage-collection lifetime.'
            );

            return false;
        }

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'DELETE FROM sessions
                  WHERE last_activity <
                        UTC_TIMESTAMP() - INTERVAL ? SECOND'
            );

            mysqli_stmt_bind_param(
                $statement,
                'i',
                $maxLifetime
            );

            mysqli_stmt_execute($statement);

            $deleted = mysqli_stmt_affected_rows($statement);

            mysqli_stmt_close($statement);

            return $deleted;
        } catch (mysqli_sql_exception $exception) {
            error_log(
                'Unable to remove expired PHP sessions from MySQL: ' .
                $exception->getMessage()
            );

            return false;
        }
    }

    /**
     * Creates a cryptographically secure PHP session identifier.
     */
    public function create_sid(): string
    {
        return bin2hex(
            random_bytes(32)
        );
    }

    /**
     * Returns whether a supplied session identifier already exists.
     *
     * PHP uses this when strict session mode validates a session ID
     * received from a browser cookie.
     */
    public function validateId(string $id): bool
    {
        if (!$this->isValidSessionId($id)) {
            return false;
        }

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'SELECT 1
                   FROM sessions
                  WHERE session_id = ?
                  LIMIT 1'
            );

            mysqli_stmt_bind_param(
                $statement,
                's',
                $id
            );

            mysqli_stmt_execute($statement);

            $exists = mysqli_stmt_fetch(
                $statement
            );

            mysqli_stmt_close($statement);

            return $exists === true;
        } catch (mysqli_sql_exception $exception) {
            error_log(
                'Unable to validate the PHP session ID in MySQL: ' .
                $exception->getMessage()
            );

            return false;
        }
    }

    private function isValidSessionId(string $id): bool
    {
        return preg_match(
            '/\A[A-Za-z0-9,-]{1,128}\z/D',
            $id
        ) === 1;
    }

    private function acquireLock(string $id): bool
    {
        if ($this->lockedSessionId === $id) {
            return true;
        }

        if (
            $this->lockedSessionId !== null &&
            !$this->releaseLock()
        ) {
            return false;
        }

        $lockName = $this->lockName($id);

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'SELECT GET_LOCK(?, 10)'
            );

            mysqli_stmt_bind_param(
                $statement,
                's',
                $lockName
            );

            mysqli_stmt_execute($statement);

            $lockResult = null;

            mysqli_stmt_bind_result(
                $statement,
                $lockResult
            );

            $fetched = mysqli_stmt_fetch($statement);

            mysqli_stmt_close($statement);

            if (
                $fetched !== true ||
                (int) $lockResult !== 1
            ) {
                error_log(
                    'Unable to acquire the PHP session lock.'
                );

                return false;
            }

            $this->lockedSessionId = $id;

            return true;
        } catch (mysqli_sql_exception $exception) {
            error_log(
                'Unable to acquire the PHP session lock: ' .
                $exception->getMessage()
            );

            return false;
        }
    }

    private function releaseLock(): bool
    {
        if ($this->lockedSessionId === null) {
            return true;
        }

        $lockName = $this->lockName(
            $this->lockedSessionId
        );

        try {
            $statement = mysqli_prepare(
                $this->connection,
                'SELECT RELEASE_LOCK(?)'
            );

            mysqli_stmt_bind_param(
                $statement,
                's',
                $lockName
            );

            mysqli_stmt_execute($statement);

            $releaseResult = null;

            mysqli_stmt_bind_result(
                $statement,
                $releaseResult
            );

            $fetched = mysqli_stmt_fetch($statement);

            mysqli_stmt_close($statement);

            $this->lockedSessionId = null;

            if (
                $fetched !== true ||
                (int) $releaseResult !== 1
            ) {
                error_log(
                    'Unable to release the PHP session lock.'
                );

                return false;
            }

            return true;
        } catch (mysqli_sql_exception $exception) {
            $this->lockedSessionId = null;

            error_log(
                'Unable to release the PHP session lock: ' .
                $exception->getMessage()
            );

            return false;
        }
    }

    private function lockName(string $id): string
    {
        return hash(
            'sha256',
            'fitbit-php-session:' . $id
        );
    }
}
