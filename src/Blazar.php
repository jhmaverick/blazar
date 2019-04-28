<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use Blazar\Component\Log\Log;
use Blazar\Component\TypeRes\StrRes;
use Blazar\Core\BlazarException;
use Blazar\Core\Manifest;
use Composer\Autoload\ClassLoader;

class Blazar {

    private static $blazar_root;
    private static $instance;
    private static $started = false;

    /*
     * Mensagem de saída em caso de um fatal error
     * Códigos: 1 = construct, 2 = error_handler, 3 = exception_handler
     */
    private static $fatal_error_msg = 'Não foi possível concluir a operação. Por favor tente mais tarde.';

    /**
     * Blazar constructor.
     */
    private function __construct() {
        try {
            self::$blazar_root = dirname(__DIR__);

            // Aplica configurações do framework
            Manifest::apply();

            // Captura Exceções não tratadas
            set_exception_handler(function (Throwable $e) {
                Log::e($e, null, false, 'exception_handler');

                echo "<pre>Não foi possível concluir a operação. Por favor tente mais tarde.\n\n" . $e->getMessage();
                exit;
            });

            // Repassa os Logs do PHP para o método de logs
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                return $this->errorHandler($errno, $errstr, $errfile, $errline);
            });

            // Desabilita exibição de erros na produção
            if (CURRENT_ENV == ENV_PRODUCTION) {
                ini_set('display_errors', 'Off');
            } else {
                ini_set('display_errors', 'On');
            }
        } catch (Throwable $e) {
            Log::e($e, null, true, 'blazar-bootstrap');
            exit('Error 1 - ' . self::$fatal_error_msg);
        }
    }

    public static function getBlazarRoot() {
        return self::$blazar_root;
    }

    /**
     * Prepara o ambiente do framework sem iniciar o mapa de classes.
     *
     * Este método e chamado automaticamente pelo bootstrap ao incluir o composer.<br>
     * Chama o manifest e inicia o tratamento de erros e exceptions pelo framework.
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
            if (count(Manifest::map()) > 0) {
                $MapClass = \Blazar\Core\App::next('class');
                new $MapClass();
            } else {
                throw new BlazarException("Nenhuma aplicação para iniciar.\n" .
                    "Verifique se o arquivo \"blazar-manifest.json\" foi criado e se alguma classe foi adicionada ao índice \"map\".\n" .
                    'Para utilizar as classes do framework sem iniciar as aplicações do "map" utilize o método Blazar::prepare.');
            }
        } catch (Error|Throwable $e) {
            Log::e('Alguma exceção não foi tratada e chegou ao root', $e);
            echo "<pre>Não foi possível concluir a operação. Por favor tente mais tarde.\n\n" . $e->getMessage();
            exit;
        }

        return true;
    }

    /**
     * Pega o diretório onde esta localizado o vendor como o diretório root do projeto.
     *
     * @return string
     */
    public static function getAppRoot() {
        try {
            $reflector = new ReflectionClass(ClassLoader::class);
            $project_root = $reflector->getFileName();
            $project_root = str_replace("\\", "/", $project_root);
            $project_root = StrRes::replaceLast($project_root, '/vendor/composer/ClassLoader.php', '');

            return $project_root;
        } catch (Exception $e) {
            echo 'Falha ao pegar diretório root';
            exit;
        }
    }

    /**
     * Pega a URL_BASE da aplicação com base no arquivo que recebeu a requisição(Provavelmente o index.php).
     *
     * A URL será formada pelo domínio e o caminho de diretórios ate o diretório público(Caso o site não esteja na raiz do domínio).
     *
     * @return string
     */
    public static function getURLBase() {
        try {
            // Pega o diretório do arquivo que iniciou a execução
            $app_dir = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
            $app_dir = implode('/', explode('/', $app_dir, -1));

            // Trata a base da URL onde o sistema foi iniciado
            $dir = explode(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), str_replace('\\', '/', $app_dir));
            $base = '//' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . '/' . end($dir) . '/');

            return $base;
        } catch (Exception|Error $e) {
            Log::e($e);
            return null;
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
                Log::e($error_str, null, true, 'error_handler');
            } else {
                Log::w($error_str, null, true, 'error_handler');
            }

            if ($fatal) {
                exit('Error 2.1 - ' . self::$fatal_error_msg);
            }

            return true;
        } catch (Throwable $e) {
            Log::e($e, null, false);
            exit('Error 2.2 - ' . self::$fatal_error_msg);
        }
    }
}