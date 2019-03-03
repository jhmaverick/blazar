<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

require_once '../../vendor/autoload.php';

try {
    // Aplicação
    Blazar::init();
} catch (\Blazar\Core\BlazarException $e) {
    echo '<pre>';
    print_r($e);
}