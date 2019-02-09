<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use Blazar\Application;
use Blazar\System\Log;

try {
    // Define codificação do Projeto
    mb_internal_encoding("UTF-8");
    mb_http_output('UTF-8');

    // Pega os dados do composer
    $composer_data = json_decode(file_get_contents(__DIR__ . "/../composer.json"), true);
    $php_required = isset($composer_data['require']['php']) ? str_replace("^", "", $composer_data['require']['php']) : null;

    // Checa versão do PHP
    if (version_compare(PHP_VERSION, $php_required, '<')) {
        header('Content-Type: text/html; charset=utf-8');
        exit("A versão do PHP é incompatível com o Framework.<br><br>\n\n" .
            "Instale o PHP " . $php_required . " ou superior.");
    }

    // Porta usada
    $port = ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) ? ":" . $_SERVER['SERVER_PORT'] : "";

    // Verifica se a constante ROOT foi definida manualmente
    if (!defined("ROOT")) {
        // Pega o diretorio do arquivo que iniciou a execução
        $real_dir = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
        $real_dir = implode("/", explode("/", $real_dir, -1));

        /**
         * O diretório raiz do projeto
         *
         * Esta constante pode ser definida manualmente antes da inclusão do autoload do composer
         */
        define("ROOT", str_replace("\\", "/", $real_dir));
    }

    // Verifica se a constante BASE foi definida manualmente
    if (!defined("BASE")) {
        // Trata a base da URL onde o sistema foi iniciado
        $dir = explode(str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']), str_replace("\\", "/", ROOT));
        $base = '//' . str_replace("//", "/", $_SERVER['HTTP_HOST'] . $port . '/' . end($dir) . "/");

        /**
         * URL seguida do caminho ate o diretorio onde o index foi iniciado
         *
         * Esta constante pode ser definida manualmente antes da inclusão do autoload do composer
         */
        define("BASE", $base);
    } else if (substr(BASE, -1) !== "/") {
        exit("A constante BASE deve terminar com \"/\".");
    }

    /** Protocolo de acesso: http ou https */
    define("HTTP", (isset($_SERVER['HTTPS']) ? "https" : "http"));

    /** O diretório raiz onde esta localizado o framework no vendor */
    define("BLAZAR_ROOT", str_replace("\\", "/", __DIR__));

    /** URL real atual completa */
    define("URL", "//" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

    /** Dominio do site */
    define("DOMAIN", "//" . $_SERVER['HTTP_HOST'] . $port);

    // Auto load para as classes do sistema
    function autoload($class_path) {
        $class_path = str_replace("\\", "/", $class_path);
        $class_path = trim($class_path, "/");

        try {
            // Retorna uma Exceção caso exista um arquivo com mesmo nome no projeto e no framework
            if (
                file_exists(ROOT . "/" . $class_path . '.php') &&
                file_exists(BLAZAR_ROOT . "/" . $class_path . '.php')
            ) {
                new Exception("Já existe uma classe com nome \"$class_path\" no framework.");
            }

            if (file_exists(ROOT . "/" . $class_path . '.php') || file_exists(BLAZAR_ROOT . "/" . $class_path . '.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once $class_path . '.php';
            }
        } catch (Exception|Error $e) {
            Log::e("AutoLoad", $e);
            exit("Não foi possível concluir a operação. Por favor tente mais tarde.");
        }
    }

    spl_autoload_register("autoload");

    // Tipos de ambientes de execução do sistema
    define("ENV_PRODUCTION", 1);
    define("ENV_TESTING", 2);
    define("ENV_DEVELOPMENT", 3);

    $environment = ENV_PRODUCTION;

    // Configurações de variaveis de ambiente
    $env_path = null;
    if (file_exists(ROOT . "/.env")) $env_path = ROOT;
    else if (file_exists(ROOT . "/../.env")) $env_path = ROOT . "/../";

    // Verifica se existe um arquivo .env
    if ($env_path !== null) {
        $dotenv = new Dotenv\Dotenv($env_path);
        $dotenv->load();
    }

    // Verifica se o tipo de ambiente foi setado em uma variavel de ambiente
    $custom_env = getenv('ENVIRONMENT_TYPE');
    if ($custom_env == ENV_DEVELOPMENT || $custom_env == ENV_TESTING || $custom_env == ENV_PRODUCTION) {
        $environment = (int)$custom_env;
    }

    /**
     * Ambiente onde o sistema esta rodando.
     * ENV_DEVELOPMENT, ENV_TESTING ou ENV_PRODUCTION.
     */
    define("CURRENT_ENV", $environment);

    // Desabilita exibição de erros na produção
    if (CURRENT_ENV == ENV_PRODUCTION) ini_set('display_errors', 'Off');
    else ini_set('display_errors', 'On');

    // Aplica configurações do framework
    Application::prepare();
} catch (Throwable|Error|Exception $e) {
    Log::e("Erro ao configurar o framework", $e);
    exit("Não foi possível concluir a operação. Por favor tente mais tarde.");
}