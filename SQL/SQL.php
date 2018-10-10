<?php

namespace Kyle;

use Kyle\SQL\Query;

use Kyle\Exceptions\RequestException;
use Kyle\Exceptions\RollbackException;

class SQL {
    private static $cons, $defaultCredentials, $commitKeys = [];

    private $database;
    private $transaction = false;

    CONST DEFAULT_CONNECTIONS_LOCATION = __DIR__ . '/../sql_credentials.json';
    CONST DEFAULT_CONNECTION = IS_PRODUCTION
        ? 'kyle_prod'
        : 'kyle_dev';
    CONST DEFAULT_CONNECTION_PORT = 3306;
    CONST ERROR_DUPLICATE_KEY = '23000';

    /**
     * SQL constructor.
     * @param string|null $database
     * @param array|null $altCredentials
     * @throws RequestException
     * @throws RollbackException
     */
    public function __construct(string $database = null, array $altCredentials = null) {
        self::loadDefaultCredentials(self::DEFAULT_CONNECTIONS_LOCATION);

        if(is_null($altCredentials) || !isset($altCredentials[0]))
            $altCredentials = [];

        $db = $this->updateCon($database, $altCredentials);
        if(!$db)
            throw new RequestException('Invalid Database.');

        if($this->getCon()->inTransaction())
            $this->startTransaction();
    }

    public function execute(string $query, array $escapeValues = [], bool $single = false): ?Query {
        $query = new Query($this, $query, empty($escapeValues) ? [] : $escapeValues, $single);

        return $query;
    }

    /**
     * Method: startTransaction
     * Start a transaction for current connection (if the connection is not already engaged in a transaction).
     * If this instance of SQL is inside transaction, roll it back prior to starting new transaction.
     *
     * If $commitKey is provided, the same key must be provided again to commit the transaction.
     *
     * @param string $commitKey
     *
     * @return bool
     * @throws RollbackException
     */
    public function startTransaction(string $commitKey = null): bool {
        if(is_null($this->getCommitKey())) {
            if($this->transaction)
                $this->rollback();

            if(!is_null($commitKey))
                $this->setCommitKey($commitKey);
        }

        if(!$this->getCon()->inTransaction())
            $this->getCon()->beginTransaction();

        $this->transaction = $this->getCon()->inTransaction();
        return $this->transaction;
    }

    /**
     * Method: commit
     * Commit the current SQL connection's transaction; if a commit key is set, only commit if the passed key equals the lock key.
     *
     * @param string $commitKey
     */
    public function commit(string $commitKey = null) {
        if($this->transaction && (!$this->getCommitKey() || $commitKey == $this->getCommitKey())) {
            $this->getCon()->commit();
            $this->transaction = false;

            $this->releaseCommit();
        }
    }

    /**
     * Method: rollback
     * Rollback current transaction; if rollback is result of failed query, throw RollbackException
     *
     * @param RequestException|null $e
     * @throws RollbackException
     */
    public function rollback(RequestException $e = null) {
        if($this->transaction) {
            $this->getCon()->rollBack();
            $this->transaction = false;

            $this->releaseCommit();

            if(!is_null($e))
                throw new RollbackException('Forced rollback: ' . $e->getMessage(), $e->getCode(), $e->getPrevious(), [], $e->getErredQuery());
        }
    }

    private function releaseCommit() {
        unset(self::$commitKeys[$this->database]);
    }

    private function setCommitKey(string $commitKey) {
        if(empty(self::$commitKeys[$this->database]))
            self::$commitKeys[$this->database] = $commitKey;
    }

    private function getCommitKey(): ?string {
        if(array_key_exists($this->database, self::$commitKeys))
            return self::$commitKeys[$this->database];

        return null;
    }

    /**
     * method: getCon
     * Returns the current PDO object or null if not set
     *
     * @return null|\PDO
     */
    public function getCon(): ?\PDO {
        return array_key_exists($this->database, self::$cons)
            ? self::$cons[$this->database]
            : null;
    }

    /**
     * method: updateCon
     * Sets $this->database and self::$cons[$this->database] based on the provided $database.
     * If $database is a string, set con to credentials in self::$defaultCredentials at index $database.
     * If $database is an array, it must include indexes: host, user, pass, db.
     * port (optional) - defaults to self::DEFAULT_CONNECTION_PORT
     *
     * @param null|string|array $database
     * @param array $altCredentials
     * @return bool
     */
    public function updateCon($database = null, array $altCredentials = []): bool {
        $credentials = [];

        if(is_string($database) && array_key_exists($database, self::$defaultCredentials))
            $credentials = self::$defaultCredentials[$database];
        else if(is_array($database) && array_key_exists('host', $database) && array_key_exists('user', $database)
            && array_key_exists('pass', $database) && array_key_exists('db', $database))
            $credentials = $database;
        else if(is_null($database))
            $credentials = self::$defaultCredentials[self::DEFAULT_CONNECTION];

        if(is_array($credentials) && !empty($credentials)) {
            $this->database = is_string($database)
                ? $database
                : (is_null($database) ? self::DEFAULT_CONNECTION : null);

            $dsn = 'mysql:host=' . $credentials['host']
            . ';port=' . ($credentials['port'] ?: self::DEFAULT_CONNECTION_PORT)
            . ';dbname=' . (isset($altCredentials[2]) ? $altCredentials[2] : $credentials['db'])
            . ';charset=utf8mb4';

            self::$cons[$this->database] = new \PDO(
                $dsn,
                isset($altCredentials[0]) ? $altCredentials[0] : $credentials['user'],
                isset($altCredentials[1]) ? $altCredentials[1] : $credentials['pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Example credentials file:
    {
        "rooms_api_dev": {
            "host": "localhost",
            "user": "sys",
            "pass": "",
            "db": "rooms_api"
        }
    }
     *
     * @param string $file
     * @return bool
     */
    public static function loadDefaultCredentials(string $file): bool {
        if(!empty(self::$defaultCredentials))
            return true;

        if(file_exists($file)) {
            $credentials = file_get_contents($file);
            if($parsed = json_decode($credentials, true)) {
                $defaultCredentials = [];
                self::$defaultCredentials = $defaultCredentials;

                if(!empty($parsed)) {
                    foreach($parsed as $name => $connection) {
                        if(array_key_exists('host', $connection) && array_key_exists('user', $connection)
                            && array_key_exists('pass', $connection) && array_key_exists('db', $connection)) {
                            $defaultCredentials[$name] = [
                                'host' => $connection['host'],
                                'user' => $connection['user'],
                                'pass' => $connection['pass'],
                                'db'   => $connection['db'],
                                'port' => @$connection['port'] ?: self::DEFAULT_CONNECTION_PORT
                            ];
                        }
                    }
                }

                self::$defaultCredentials = $defaultCredentials;
                return true;
            }
        }

        return false;
    }

    /**
     * Method: closeConnections
     * Destroy the PDO objects in self::$cons, thus closing connection.
     */
    public static function closeConnections() {
        if(is_array(self::$cons) && count(self::$cons) >= 1) {
            foreach(self::$cons as $id => $con) {
                self::$cons[$id] = null;
            }
        }
    }

    /**
     * Method: unsafeFillEscapeValues
     * FOR DEVELOPMENT PURPOSES ONLY
     * Fills value placeholders (?) in $query with corresponding value in $escapeValues
     *
     * @param string $query
     * @param array $escapeValues
     * @return string
     */
    public static function unsafeFillEscapeValues(string $query, array $escapeValues): string {
        $query = str_replace('?', '%s', $query);

        foreach($escapeValues as $i => $escapeValue) {
            $escapeValues[$i] = '"' . $escapeValue . '"';
        }

        return vsprintf($query, $escapeValues);
    }
}
