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

class Blazar {
    private static $instance;
    private static $started = false;

    /*
     * Mensagem de saída em caso de um fatal error
     * Códigos: 1 = construct, 2 = autoload, 3 = error_handler e 4 = exception_handler
     * set_exception_handler,
     */
    private const FATAL_ERROR_MSG = 'Não foi possível concluir a operação. Por favor tente mais tarde.';

    /**
     * Blazar constructor.
     */
    private function __construct() {
        try {
            // Define codificação do Projeto
            mb_internal_encoding('UTF-8');
            mb_http_output('UTF-8');

            // Constantes do Framework
            $this->constants();

            // Captura Exceções não tratadas
            set_exception_handler(function ($e) {
                \Blazar\Core\Log::e($e, null, false, 'exception_handler');
                exit('Error 4 - ' . self::FATAL_ERROR_MSG);
            });

            // Repassa os Logs do PHP para o método de logs
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                return $this->errorHandler($errno, $errstr, $errfile, $errline);
            });

            // Auto load para as classes do sistema
            spl_autoload_register(function ($class_path) {
                $this->autoloader($class_path);
            });

            // Aplica configurações do framework
            new \Blazar\Core\Manifest();

            // Desabilita exibição de erros na produção
            if (CURRENT_ENV == ENV_PRODUCTION) {
                ini_set('display_errors', 'Off');
            } else {
                ini_set('display_errors', 'On');
            }
        } catch (Throwable $e) {
            \Blazar\Core\Log::e($e, null, true, 'blazar-bootstrap');
            exit('Error 1 - ' . self::FATAL_ERROR_MSG);
        }
    }

    /**
     * Prepara o ambiente do framework sem iniciar o mapa de classes.
     *
     * Esse método habilita o autoload de classes, inicia as constantes do sistema, Faz a leitura do manifest e inicia
     * o tratamento de erros pelo framework.
     *
     * @return bool
     */
    public static function prepare(): bool {
        if (self::$instance === null) {
            self::$instance = new Blazar();

            return true;
        } else {
            return false;
        }
    }

    /**
     * Iniciar a aplicação a partir do mapa de classes do Manifest.
     *
     * @return bool
     */
    public static function init(): bool {
        if (self::$instance === null) {
            self::$instance = new Blazar();
        }

        // Impede que a função seja iniciada mais de uma vez
        if (self::$started) {
            return false;
        }
        self::$started = true;

        try {
            if (count(\Blazar\Core\Manifest::map()) > 0) {
                $MapClass = \Blazar\Core\App::next('class');
                new $MapClass();
            } else {
                throw new \Blazar\Core\BlazarException("Nenhuma aplicação para iniciar.\n" .
                    "Verifique se o arquivo \"blazar-manifest.json\" foi criado e se alguma classe foi adicionada ao índice \"map\".\n" .
                    'Para utilizar as classes do framework sem iniciar as aplicações do "map" utilize o método Blazar::prepare.');
            }
        } catch (\Error | Throwable $e) {
            \Blazar\Core\Log::e('Alguma exceção não foi tratada e chegou ao root', $e);
            exit('Não foi possível concluir a operação. Por favor tente mais tarde.');
        }

        return true;
    }

    /**
     * Pega o diretório do arquivo que recebeu a requisição(Provavelmente o index.php) para ser usado como root do diretório público e source.
     *
     * @return string
     */
    public static function getAppRoot() {
        // Pega o diretório do arquivo que iniciou a execução
        $app_dir = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
        $app_dir = implode('/', explode('/', $app_dir, -1));

        return $app_dir;
    }

    /**
     * Pega a URL_BASE da aplicação com base no arquivo que recebeu a requisição(Provavelmente o index.php).
     *
     * A URL será formada pelo domínio e o caminho de diretórios ate o diretório público(Caso o site não esteja na raiz do domínio).
     *
     * @return string
     */
    public static function getURLBase() {
        $app_dir = self::getAppRoot();

        // Porta usada
        $port = ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) ? ':' . $_SERVER['SERVER_PORT'] : '';

        // Trata a base da URL onde o sistema foi iniciado
        $dir = explode(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), str_replace('\\', '/', $app_dir));
        $base = '//' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . $port . '/' . end($dir) . '/');

        return $base;
    }

    /**
     * Constantes do Framework.
     */
    private function constants() {
        // Porta usada
        $port = ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) ? ':' . $_SERVER['SERVER_PORT'] : '';

        /* Ambiente de Produção */
        define('ENV_PRODUCTION', 1);

        /* Ambiente de Teste */
        define('ENV_TESTING', 2);

        /* Ambiente de desenvolvimento */
        define('ENV_DEVELOPMENT', 3);

        /* O diretório raiz onde esta localizado o framework no vendor */
        define('BLAZAR_DIR', str_replace('\\', '/', __DIR__));

        if (!defined('SOURCE_DIR')) {
            /*
             * O caminho ate o diretório raiz do código fonte
             *
             * Esta constante pode ser definida manualmente antes da chamada do método prepare ou init
             */
            define('SOURCE_DIR', str_replace('\\', '/', self::getAppRoot()));
        }

        if (!defined('RESOURCE_DIR')) {
            /*
             * O caminho ate a raiz do diretório público
             *
             * Esta constante pode ser definida manualmente antes da chamada do método prepare ou init
             */
            define('RESOURCE_DIR', str_replace('\\', '/', self::getAppRoot()));
        }

        if (!defined('URL_BASE')) {
            /*
             * URL seguida do caminho ate o diretório onde o index foi iniciado
             *
             * Esta constante pode ser definida manualmente antes da chamada do método prepare ou init
             */
            define('URL_BASE', self::getURLBase());
        } elseif (substr(URL_BASE, -1) !== '/') {
            exit('A constante "URL_BASE" deve terminar com "/".');
        }

        if (!defined('URL')) {
            /* URL real atual completa */
            define('URL', '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $port);
        }
    }

    /**
     * Autoload para classes do Framework e Projeto.
     *
     * Faz o include da classe buscando um diretório e arquivo com mesmo nome do namespace
     *
     * @param $class_path
     */
    private function autoloader($class_path) {
        try {
            $class_path = str_replace('\\', '/', $class_path);
            $class_path = trim($class_path, '/');

            // Retorna uma Exceção caso exista um arquivo com mesmo nome no projeto e no framework
            if (
                file_exists(SOURCE_DIR . '/' . $class_path . '.php') &&
                file_exists(BLAZAR_DIR . '/' . $class_path . '.php')
            ) {
                new \Blazar\Core\BlazarException("Já existe uma classe com nome \"$class_path\" no framework.");
            }

            if (file_exists(BLAZAR_DIR . '/' . $class_path . '.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once BLAZAR_DIR . '/' . $class_path . '.php';
            } elseif (file_exists(SOURCE_DIR . '/' . $class_path . '.php')) {
                /** @noinspection PhpIncludeInspection */
                require_once SOURCE_DIR . '/' . $class_path . '.php';
            }
        } catch (Throwable $e) {
            \Blazar\Core\Log::e($e, null, false, 'spl_autoload');
            exit('Error 2 - ' . self::FATAL_ERROR_MSG);
        }
    }

    /**
     * Repassa os Logs do PHP para o método de logs.
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     *
     * @return bool
     */
    private function errorHandler($errno, $errstr, $errfile, $errline) {
        // @ suppression used, don't worry about it
        if (error_reporting() == 0) {
            return false;
        }

        global $log_ignore_errors;
        if (isset($log_ignore_errors) && $log_ignore_errors == true) {
            return false;
        }

        try {
            $error_list = [
                E_ERROR => 'E_ERROR',
                E_WARNING => 'E_WARNING',
                E_PARSE => 'E_PARSE',
                E_NOTICE => 'E_NOTICE',
                E_CORE_ERROR => 'E_CORE_ERROR',
                E_CORE_WARNING => 'E_CORE_WARNING',
                E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                E_USER_ERROR => 'E_USER_ERROR',
                E_USER_WARNING => 'E_USER_WARNING',
                E_USER_NOTICE => 'E_USER_NOTICE',
                E_STRICT => 'E_STRICT',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_DEPRECATED => 'E_DEPRECATED',
                E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            ];

            $error_str = '[' . (isset($error_list[$errno]) ? $error_list[$errno] : $errno) . "] $errstr";
            $error_str .= ($errfile) ? " in file $errfile" . (($errline) ? " on line $errline" : '') : '';

            $err_type = 'w';
            $fatal = false;

            if (in_array($errno, [E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
                $err_type = 'e';
                $fatal = true;
            }

            if ($err_type == 'e') {
                \Blazar\Core\Log::e($error_str, null, true, 'error_handler');
            } else {
                \Blazar\Core\Log::w($error_str, null, true, 'error_handler');
            }

            if ($fatal) {
                exit('Error 3.1 - ' . self::FATAL_ERROR_MSG);
            }

            return true;
        } catch (Throwable $e) {
            \Blazar\Core\Log::e($e, null, false, 'spl_autoload');
            exit('Error 3.2 - ' . self::FATAL_ERROR_MSG);
        }
    }
}