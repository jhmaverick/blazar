<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// Checa versão do PHP
if (version_compare(PHP_VERSION, "7.1", '<')) {
    header('Content-Type: text/html; charset=utf-8');
    exit("A versão do PHP é incompatível com o Framework.<br><br>\n\n" .
        "Instale o PHP 7.1 ou superior.");
}

// Inicia configuração do Framework
new class() {

    /**
     * Mensagem de saida em caso de um fatal error
     * Códigos: 1 = construct, 2 = autoload, 3 = error_handler e 4 = exception_handler
     * set_exception_handler,
     */
    private const FATAL_ERROR_MSG = "Não foi possível concluir a operação. Por favor tente mais tarde.";

    public function __construct() {
        try {
            // Define codificação do Projeto
            mb_internal_encoding("UTF-8");
            mb_http_output('UTF-8');

            // Constantes do Framework
            $this->constants();

            // Captura Exceções não tratadas
            set_exception_handler(function ($e) {
                \Blazar\System\Log::e($e, null, false, "exception_handler");
                exit("Error 4 - " . self::FATAL_ERROR_MSG);
            });

            // Repassa os Logs do PHP para o método de logs
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                return $this->errorHandler($errno, $errstr, $errfile, $errline);
            });

            // Auto load para as classes do sistema
            spl_autoload_register(function ($class_path) {
                $this->autoloader($class_path);
            });

            // Desabilita exibição de erros na produção
            if (CURRENT_ENV == ENV_PRODUCTION) ini_set('display_errors', 'Off');
            else ini_set('display_errors', 'On');

            // Aplica configurações do framework
            new \Blazar\Manifest();
        } catch (Throwable|Error|Exception $e) {
            \Blazar\System\Log::e($e, null, true, "blazar-bootstrap");
            exit("Error 1 - " . self::FATAL_ERROR_MSG);
        }
    }

    /**
     * Constantes do Framework
     */
    private function constants() {
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
            // Porta usada
            $port = ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) ? ":" . $_SERVER['SERVER_PORT'] : "";

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
            exit("A constante \"BASE\" deve terminar com \"/\".");
        }

        /** O diretório raiz onde esta localizado o framework no vendor */
        define("BLAZAR_ROOT", str_replace("\\", "/", __DIR__));

        /** Protocolo de acesso: http ou https */
        define("HTTP", (isset($_SERVER['HTTPS']) ? "https" : "http"));

        /** URL real atual completa */
        define("URL", "//" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

        /** Ambiente de Produção */
        define("ENV_PRODUCTION", 1);

        /** Ambiente de Teste */
        define("ENV_TESTING", 2);

        /** Ambiente de desenvolvimento */
        define("ENV_DEVELOPMENT", 3);

        /**
         * Ambiente onde o sistema esta rodando.
         *
         * ENV_DEVELOPMENT, ENV_TESTING ou ENV_PRODUCTION.
         */
        define("CURRENT_ENV", $this->getCurrentEnv());
    }

    /**
     * Pega o ambiente atual
     *
     * @return int ENV_PRODUCTION, ENV_TESTING ou ENV_DEVELOPMENT
     */
    private function getCurrentEnv() {
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

        return $environment;
    }

    /**
     * Autoload para classes do Framework e Projeto
     *
     * Faz o include da classe buscando um diretório e arquivo com mesmo nome do namespace
     *
     * @param $class_path
     */
    private function autoloader($class_path) {
        try {
            $class_path = str_replace("\\", "/", $class_path);
            $class_path = trim($class_path, "/");

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
        } catch (Throwable|Error|Exception $e) {
            \Blazar\System\Log::e($e, null, false, "spl_autoload");
            exit("Error 2 - " . self::FATAL_ERROR_MSG);
        }
    }

    /**
     * Repassa os Logs do PHP para o método de logs
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     *
     * @return bool
     */
    private function errorHandler($errno, $errstr, $errfile, $errline) {
        global $log_ignore_errors;
        if (isset($log_ignore_errors) && $log_ignore_errors == true) return false;

        try {
            $error_list = [
                E_ERROR => "E_ERROR",
                E_WARNING => "E_WARNING",
                E_PARSE => "E_PARSE",
                E_NOTICE => "E_NOTICE",
                E_CORE_ERROR => "E_CORE_ERROR",
                E_CORE_WARNING => "E_CORE_WARNING",
                E_COMPILE_ERROR => "E_COMPILE_ERROR",
                E_COMPILE_WARNING => "E_COMPILE_WARNING",
                E_USER_ERROR => "E_USER_ERROR",
                E_USER_WARNING => "E_USER_WARNING",
                E_USER_NOTICE => "E_USER_NOTICE",
                E_STRICT => "E_STRICT",
                E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
                E_DEPRECATED => "E_DEPRECATED",
                E_USER_DEPRECATED => "E_USER_DEPRECATED"
            ];

            $error_str = "[" . (isset($error_list[$errno]) ? $error_list[$errno] : $errno) . "] $errstr";
            $error_str .= ($errfile) ? " in file $errfile" . (($errline) ? " on line $errline" : "") : "";

            $err_type = "w";
            $fatal = false;

            if (in_array($errno, [E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
                $err_type = "e";
                $fatal = true;
            }

            if ($err_type == "e") \Blazar\System\Log::e($error_str, null, true, "error_handler");
            else \Blazar\System\Log::w($error_str, null, true, "error_handler");

            if ($fatal) exit("Error 3.1 - " . self::FATAL_ERROR_MSG);

            return true;
        } catch (Throwable|Error|Exception $e) {
            \Blazar\System\Log::e($e, null, false, "spl_autoload");
            exit("Error 3.2 - " . self::FATAL_ERROR_MSG);
        }
    }
};