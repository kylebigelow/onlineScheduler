<?php

namespace Kyle\Exceptions;

use Kyle\GeneralException;
use Kyle\SQL\Query;

class DatabaseException extends GeneralException {
    private $erredQuery = null;

    public function __construct(string $message = "", int $code = 0, \PDOException $previous = null, array $params = [], Query $query = null) {
        $this->erredQuery = $query;

        parent::__construct($message, $code, $previous);
    }

    public function getErredQuery(): ?Query {
        return $this->erredQuery instanceof Query
            ? $this->erredQuery
            : null;
    }
}
