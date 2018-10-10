<?php

namespace Kyle\SQL;

use Kyle\Exceptions\RequestException;
use Kyle\Exceptions\RollbackException;
use Kyle\SQL;

class Query {
    /**
     * @var null|SQL
     */
    private $sql = null;
    /**
     * @var null|\PDO
     */
    private $db = null;

    /**
     * @var null|\PDOStatement
     */
    private $prevQuery = null;
    /**
     * @var null|\PDOStatement
     */
    private $query = null;

    private $prevRawQuery = null;
    private $rawQuery = null;

    /**
     * @var $prevResult array|null|QueryError
     * @var $result array|null|QueryError
     * @var $prevError QueryError|null
     * @var $error QueryError|null
     */
    private $prevResult = null;
    private $result = null;
    private $prevError = null;
    private $error = null;

    private $prevEscaped = null;
    private $escaped = null;

    private $prevEscapeValues = null;
    private $escapeValues = null;

    private $prevTransaction = null;
    private $transaction = null;

    private $executions = 0;

    /**
     * Query constructor.
     * @param SQL $db
     * @param string $rawQuery
     * @param array $escapeValues
     * @param bool $single
     * @param bool $execute
     * @throws RequestException
     * @throws RollbackException
     */
    public function __construct(SQL $db = null, string $rawQuery, array $escapeValues = [], bool $single = false, bool $execute = true) {
        $this->sql = $db;
        $this->db = $this->sql->getCon();

        if($execute)
            $this->execute($rawQuery, $escapeValues, $single);
    }

    /**
     * Method: execute
     * Helper method for making SQL queries.
     *
     * Pass only $query to execute basic SQL Query.
     * Pass both $query and $params to create new prepared query and execute with first set of values;
     * To make subsequent queries on same prepared statement, pass null $query and parameters to repeat with new values.
     *
     * @param string|null $query
     * @param array $params
     * @param bool $single
     * @return null
     * @throws RequestException
     * @throws RollbackException
     */
    public function execute(string $query = null, array $params = [], bool $single = false) {
        if($this->getExecutions() > 0)
            $this->makeResultsPreviousResults();

        $this->transaction = $this->db->inTransaction();

        try {
            if(is_string($query)) {
                $this->rawQuery = $query;

                if(empty($params)) {
                    $this->escaped = false;
                    $this->_executeBasic($query, $single);
                } else {
                    $this->escaped = true;
                    $this->escapeValues = $params;
                    $this->_executePrepared($query, $params, $single);
                }
            } else if($this->executions > 0 && is_null($query)) {
                if($this->prevEscaped) {
                    if(empty($params)) // If new escape values not provided, repeat query with same values
                        $this->escapeValues = $params = $this->getEscapeValues(true);

                    $this->rawQuery = $this->getRawQuery(true);
                    $this->escaped = true;

                    $this->_executePrepared($this->getRawQuery(), $params, $single);
                } else if(!$this->prevEscaped && empty($params)) {
                    $this->rawQuery = $this->getRawQuery(true);
                    $this->escaped = false;

                    $this->_executeBasic($this->getRawQuery(), $single);
                }
            }

            $this->executions++;
            return $this->getResult();
        } catch(RequestException $e) {
            $this->error = $this->result = new QueryError($e);

            if($this->transaction) {
                $this->sql->rollback($e);
            } else throw $e;
        }
    }

    /**
     * Method: _executePrepared
     * Execute a prepared SQL query; if $query not provided, use the existing prepared statement with new values
     *
     * @param string|null $query
     * @param array $params
     * @param bool $single
     * @throws RequestException
     */
    private function _executePrepared(string $query = null, array $params = [], bool $single) {
        $method = substr(is_string($query) ? $query : $this->getRawQuery(true), 0, 6);

        try {
            if(is_string($query)) {
                $this->query = $this->db->prepare($query);
            } else {
                if(!$this->prevEscaped)
                    throw new RequestException('Could not use previous query because it was not escaped.');
            }

            $this->query->execute($params);

            switch($method) {
                case 'SELECT':
                    if(!$single) $this->result = $this->query->fetchAll(\PDO::FETCH_ASSOC);
                    else $this->result = $this->query->fetch(\PDO::FETCH_ASSOC);
                    break;
                case 'INSERT':
                case 'UPDATE':
                case 'DELETE':
                    $this->result = ['insert_id' => $this->db->lastInsertId(), 'rows_affected' => $this->query->rowCount()];
                    break;
            }
        } catch(\PDOException $e) {
            $this->result = false;

            $code = is_int($e->getCode()) ? $e->getCode() : 0;

            throw new RequestException('Could not ' . $method . ':' . $e->getMessage(), $code, $e, [], $this);
        }
    }

    /**
     * Method: _executeBasic
     * Execute a regular SQL query. Update $this->lastResult.
     *
     * @param string $query
     * @param bool $single
     * @throws RequestException
     */
    private function _executeBasic(string $query, bool $single) {
        $method = substr($query, 0, 6);

        try {
            $this->query = $this->db->query($query);

            switch($method) {
                case 'SELECT':
                    if(!$single) $this->result = $this->query->fetchAll(\PDO::FETCH_ASSOC);
                    else $this->result = $this->query->fetch(\PDO::FETCH_ASSOC);
                    break;
                case 'INSERT':
                case 'UPDATE':
                case 'DELETE':
                    $this->result = ['insert_id' => $this->db->lastInsertId(), 'rows_affected' => $this->query->rowCount()];
                    break;
                case 'CREATE':
                    $this->result = true;
                    break;
            }
        } catch(\PDOException $e) {
            $this->result = false;

            $code = is_int($e->getCode()) ? $e->getCode() : 0;

            throw new RequestException('Could not ' . $method . ': ' . $e->getMessage(), $code, $e, [], $this);
        }
    }

    /**
     * Method: getInsertId
     * Returns insert ID for last request if of type INSERT, UPDATE or DELETE; null otherwise.
     *
     * @param bool $prev
     * @return int|null
     */
    public function getInsertId(bool $prev = false): ?int {
        switch($prev) {
            case true:
                return is_array($this->prevResult) && array_key_exists('insert_id', $this->prevResult)
                    ? $this->prevResult['insert_id']
                    : null;
            case false:
                return is_array($this->result) && array_key_exists('insert_id', $this->result)
                    ? $this->result['insert_id']
                    : null;
        }

    }

    /**
     * Method: getAffectedRows
     * Returns number of affected rows for last request if of type INSERT, UPDATE or DELETE; null otherwise.
     *
     * @param bool $prev
     * @return int|null
     */
    public function getAffectedRows(bool $prev = false): ?int {
        switch($prev) {
            case true:
                return is_array($this->prevResult) && array_key_exists('rows_affected', $this->prevResult)
                    ? $this->prevResult['rows_affected']
                    : null;
            case false:
                return is_array($this->result) && array_key_exists('rows_affected', $this->result)
                    ? $this->result['rows_affected']
                    : null;
        }
    }

    /**
     * Method: getRawQuery
     * Returns the (current/prev) raw query. $unsafeSimulateEscaped should only be used in development;
     * see SQL::unsafeFillEscapeValues() for functionality details.
     *
     * @param bool $prev
     * @param bool $unsafeSimulateEscaped
     * @return null|string
     */
    public function getRawQuery(bool $prev = false, $unsafeSimulateEscaped = false): ?string {
        switch($prev) {
            case true:
                return is_string($this->prevRawQuery)
                    ? ($unsafeSimulateEscaped && $this->prevEscaped
                        ? SQL::unsafeFillEscapeValues($this->prevRawQuery, $this->prevEscaped)
                        : $this->prevRawQuery)
                    : null;
            case false:
                return is_string($this->rawQuery)
                    ? ($unsafeSimulateEscaped && $this->escaped
                        ? SQL::unsafeFillEscapeValues($this->rawQuery, $this->escapeValues)
                        : $this->rawQuery)
                    : null;
        }
    }

    public function getEscapeValues(bool $prev = false): ?array {
        switch($prev) {
            case true:
                return is_array($this->prevEscapeValues)
                    ? $this->prevEscapeValues
                    : null;
            case false:
                return is_array($this->escapeValues)
                    ? $this->escapeValues
                    : null;
        }
    }

    public function getResult(bool $prev = false) { // result | error
        if($prev)
            return $this->prevResult;

        return $this->result;
    }

    public function getError(bool $prev = false): ?QueryError {
        switch($prev) {
            case true:
                return $this->prevError instanceof QueryError
                    ? $this->prevError
                    : null;
            case false:
                return $this->error instanceof QueryError
                    ? $this->error
                    : null;
        }
    }

    public function getQuery(bool $prev = false): ?\PDOStatement {
        switch($prev) {
            case true:
                return $this->prevQuery instanceof \PDOStatement
                    ? $this->prevQuery
                    : null;
            case false:
                return $this->query instanceof \PDOStatement
                    ? $this->query
                    : null;
        }
    }

    public function getExecutions(): int {
        return $this->executions;
    }

    public function makeResultsPreviousResults() {
        $this->prevQuery        = $this->query;
        $this->prevError        = $this->error;
        $this->prevResult       = $this->result;
        $this->prevEscaped      = $this->escaped;
        $this->prevRawQuery     = $this->rawQuery;
        $this->prevTransaction  = $this->transaction;
        $this->prevEscapeValues = $this->escapeValues;

        $this->query        = null;
        $this->error        = null;
        $this->result       = null;
        $this->escaped      = null;
        $this->rawQuery     = null;
        $this->escapeValues = null;
    }

    public function __toString() {
        return $this->getRawQuery();
    }
}
