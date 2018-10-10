<?php
CONST IS_PRODUCTION = false;
require_once __DIR__ . '/GeneralException.php';
require_once __DIR__ . '/DatabaseException.php';
require_once __DIR__ . '/RequestException.php';
require_once __DIR__ . '/RollbackException.php';
require_once __DIR__ . '/SQL/Query.php';
require_once __DIR__ . '/SQL/QueryError.php';
require_once __DIR__ . '/SQL/QueryQueue.php';
require_once __DIR__ . '/SQL/SQL.php';
CONST HOMEPAGE = 'main.php';