<?php
declare(strict_types=1);

define('APP_NAME', 'ChemCards');
define('APP_SUBTITLE', 'Карточки для запоминания химических формул');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'chem_flashcards');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));
define('DEMO_SET_LIMIT', 10);
