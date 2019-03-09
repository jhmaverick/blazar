<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// Checa versão do PHP
if (version_compare(PHP_VERSION, '7.1', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    exit("A versão do PHP é incompatível com o Framework.<br><br>\n\n" .
        'Instale o PHP 7.1 ou superior.');
}

// Define codificação do Projeto
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

/** Ambiente de Produção */
define('ENV_PRODUCTION', 1);

/* Ambiente de Teste */
define('ENV_TESTING', 2);

/* Ambiente de desenvolvimento */
define('ENV_DEVELOPMENT', 3);

/* O diretório raiz onde esta localizado o framework no vendor */
define('BLAZAR_ROOT', str_replace('\\', '/', __DIR__));

if (!defined('APP_ROOT')) {
    /*
     * O caminho ate o diretório raiz do código fonte
     *
     * Esta constante pode ser definida manualmente antes da chamada do método prepare ou init
     */
    define('APP_ROOT', str_replace('\\', '/', Blazar::getProjectRoot()));
}

if (!defined('URL_BASE')) {
    /*
     * URL seguida do caminho ate o diretório onde o index foi iniciado
     *
     * Esta constante pode ser definida manualmente antes da chamada do método prepare ou init
     */
    define('URL_BASE', Blazar::getURLBase());
} elseif (substr(URL_BASE, -1) !== '/') {
    exit('A constante "URL_BASE" deve terminar com "/".');
}

if (!defined('URL')) {
    // Porta usada
    $port = ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) ? ':' . $_SERVER['SERVER_PORT'] : '';

    /* URL real atual completa */
    define('URL', '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $port);
}

Blazar::prepare();