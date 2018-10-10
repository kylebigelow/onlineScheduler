<?php

namespace Kyle\SQL;

class QueryQueue {
    private $queries = [];
    private $cursor = 0;

    public function __construct(array $queries = []) {
        if(!empty($queries)) {
            foreach($queries as $query) {
                if($query instanceof Query)
                    $this->push($query);
            }
        }
    }

    public function push(Query $query) {
        $this->queries[] = $query;
    }

    public function pop(): ?Query {
        return ($cursor = $this->moveCursor())
            ? $this->queries[$cursor]
            : null;
    }

    private function moveCursor(int $magnitude = 1): ?int {
        $newCursor = $this->cursor + $magnitude;

        if($newCursor < 0 || !array_key_exists($newCursor, $this->queries))
            return null;

        return $this->cursor = $newCursor;
    }
}
