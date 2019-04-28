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

if (!defined('ENV_PRODUCTION')) {
    // Ambiente de Produção
    define('ENV_PRODUCTION', 1);
}

if (!defined('ENV_TESTING')) {
    // Ambiente de Teste
    define('ENV_TESTING', 2);
}

if (!defined('ENV_DEVELOPMENT')) {
    // Ambiente de desenvolvimento
    define('ENV_DEVELOPMENT', 3);
}

if (!defined('APP_ROOT')) {
    /* O caminho ate o diretório raiz do código fonte
     *
     * Esta constante pode ser definida manualmente antes de incluir o composer
     */
    define('APP_ROOT', str_replace('\\', '/', Blazar::getAppRoot()));
}

if (!defined('URL_BASE')) {
    /* URL seguida do caminho ate o diretório onde o index foi iniciado
     *
     * Esta constante pode ser definida manualmente antes de incluir o composer
     */
    define('URL_BASE', Blazar::getURLBase());
} elseif (substr(URL_BASE, -1) !== '/') {
    exit('A constante "URL_BASE" deve terminar com "/".');
}

if (!defined('URL')) {
    try {
        /* RL real atual completa com a porta(Caso não seja a 80 ou a 443)
         *
         * Esta constante pode ser definida manualmente antes de incluir o composer
         */
        define('URL', '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    } catch (Exception|Error $e) {
        \Blazar\Component\Log\Log::e($e);
        return null;
    }
}

Blazar::prepare();