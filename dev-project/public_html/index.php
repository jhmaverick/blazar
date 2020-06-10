<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use Blazar\Core\Blazar;

define('APP_ROOT', dirname(__DIR__));
define("APP_LOCALE", "pt_BR");

require_once __DIR__ . '/../../vendor/autoload.php';

// Aplicação
Blazar::prepare();
Blazar::init();