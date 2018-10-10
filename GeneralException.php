<?php

namespace Kyle;

use Throwable;

class GeneralException extends \Exception {
    protected $params = [];

    public function __construct(string $message = "", int $code = 0, Throwable $previous = null, array $params = []) {
        if(!empty($params))
            $this->params = $params;

        parent::__construct($message, $code, $previous);
    }

    public function getErrorParams(): array {
        return $this->params;
    }
}
