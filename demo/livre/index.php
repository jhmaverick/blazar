<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// Auto Load para dependências do composer
require_once '../../vendor/autoload.php';

Blazar::prepare();

use Blazar\Component\FileSystem\FileSystem;
use Blazar\Component\TypeRes\StrRes;

// Constantes e métodos do Framework
echo '<b>Path resolve:</b> ' . FileSystem::pathResolve('path', 'to/file.txt') . '<br>';
echo '<b>Constante ROOT:</b> ' . SOURCE_DIR . '<br>';
echo '<b>Constante BASE:</b> ' . URL_BASE . '<br>';
echo '<b>Método StrRes::startsWith:</b> ' . (StrRes::startsWith('abcd', 'ab') ? 1 : 0);