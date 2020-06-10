<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use Blazar\Component\Log\Log;
use Blazar\Core\Blazar;
use Blazar\Core\Manifest;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

if (php_sapi_name() != "cli") {
    // Checa versão do PHP
    if (version_compare(PHP_VERSION, '7.1', '<')) {
        header('Content-Type: text/html; charset=utf-8');
        exit("A versão do PHP é incompatível com o Framework.<br><br>\n\n" .
            'Instale o PHP 7.1 ou superior.');
    }

    // Verifica se existe uma localidade definida por cookie
    //define("APP_LOCALE", "en");
    $current_locale = trim($_COOKIE['locale'] ?? null);

    if (empty($current_locale) && defined("APP_LOCALE")) {
        // Verifica se a localidade está definida na constante
        $current_locale = APP_LOCALE;
    } else if (empty($current_locale)) {
        // Procura nas linguagens da requisição
        $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($languages as $lang) {
            // Usa a primeira linguagem válida que for encontrada
            $lang = trim(str_replace("-", "_", $lang));

            if (!empty($lang) && file_exists(APP_ROOT . "/translations/$lang.php")) {
                $current_locale = $lang;
                break;
            }
        }
    }

    // Usa o inglês caso nenhum idioma seja encontrado
    $current_locale = $current_locale ?? "en";

    // Aplica a localidade no PHP
    locale_set_default($current_locale);

    // Prepara a classe de idiomas
    $GLOBALS['translator'] = new Translator($current_locale);
    $GLOBALS['translator']->addLoader('array', new PhpFileLoader());

    // Adiciona um método simplificado para carregar os textos
    function __(?string $id, array $parameters = [], string $domain = null, string $locale = null) {
        return $GLOBALS['translator']->trans($id, $parameters, $domain, $locale);
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
    } else if (substr(URL_BASE, -1) !== '/') {
        exit('A constante "URL_BASE" deve terminar com "/".');
    }

    if (!defined('URL')) {
        try {
            /* RL real atual completa com a porta(Caso não seja a 80 ou a 443)
             *
             * Esta constante pode ser definida manualmente antes de incluir o composer
             */
            define('URL', '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        } catch (Throwable $e) {
            Log::e($e);
            return null;
        }
    }

    // Aplica as configurações no ambiente
    Blazar::prepare();

    // Idiomas que serão usados caso o texto não exista no idioma em uso
    $fallback_locales = Manifest::config('fallback_locales') ?? [];
    if (!empty($fallback_locales)) {
        $GLOBALS['translator']->setFallbackLocales($fallback_locales);

        foreach ($fallback_locales as $lang) {
            if ($lang != $current_locale && !empty($lang) && file_exists(APP_ROOT . "/translations/$lang.php")) {
                $GLOBALS['translator']->addResource('array', APP_ROOT . "/translations/$lang.php", $lang);
            }
        }
    }

    // Carrega o Idioma em uso atualmente
    if (!empty($current_locale) && file_exists(APP_ROOT . "/translations/$current_locale.php")) {
        $GLOBALS['translator']->addResource('array', APP_ROOT . "/translations/$current_locale.php", $current_locale);
    }
}
