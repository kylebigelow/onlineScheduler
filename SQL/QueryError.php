<?php

namespace Kyle\SQL;

use Kyle\Exceptions\DatabaseException;

class QueryError {
    /**
     * @var null|\PDOException
     */
    private $pdoException = null;

    /**
     * @var null|DatabaseException
     */
    private $dbException = null;

    public function __construct(DatabaseException $e) {
        $this->dbException = $e;
        $this->pdoException = $this->dbException->getPrevious();
    }

    public function getException(): ?\PDOException {
        return $this->pdoException instanceof \PDOException
            ? $this->pdoException
            : null;
    }

    public function getCode() {
        if(!is_null($exception = $this->getException()))
            return $exception->getCode();
    }

    public function __toString() {
        return $this->dbException->getMessage();
    }
}
