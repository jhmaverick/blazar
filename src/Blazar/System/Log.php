<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\System;

use Blazar\Helpers\Files;
use Blazar\Helpers\Request;
use Blazar\Manifest;
use Error;
use Exception;
use Throwable;

/**
 * Controle de logs do sistema
 */
final class Log {

    // Callback para repassar o log para outro metodo
    private static $callback = null;
    // Caso algum erro ocorra no callback ele é desabilitado
    private static $callback_disabled = false;
    // Previne que o callback inicie uma chamada recursiva nos logs
    private static $callback_run = false;

    /**
     * Error Log
     *
     * @see \Blazar\System\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     */
    public static function e($log, $auxiliar = null, bool $trace = false, string $tag = null) {
        self::add("e", $log, $auxiliar, $trace, $tag);
    }

    /**
     * Warning Log
     *
     * @see \Blazar\System\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     */
    public static function w($log, $auxiliar = null, bool $trace = false, string $tag = null) {
        self::add("w", $log, $auxiliar, $trace, $tag);
    }

    /**
     * Info Log
     *
     * @see \Blazar\System\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     */
    public static function i($log, $auxiliar = null, bool $trace = false, string $tag = null) {
        self::add("i", $log, $auxiliar, $trace, $tag);
    }

    /**
     * Debug Log
     *
     * Esse log deve ser utilizado apenas para debug em desenvolvimento e removido.<br>
     * Logs de debug em produção irão gerar um log warning
     *
     * @see \Blazar\System\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     */
    public static function d($log, $auxiliar = null, bool $trace = false, string $tag = null) {
        if (CURRENT_ENV == ENV_PRODUCTION)
            self::add("w", "Um log de debug pode ter sido esquecido e entrou em produção.", null, true, $tag);

        self::add("d", $log, $auxiliar, $trace, $tag);
    }

    /**
     * Adiciona um metodo para repassar os logs recebidos
     *
     * O callback recebe um array como parâmetro com os indices: <br>
     * [type] => e|w|i|d<br>
     * [main] => array<br>
     * [main][type] => text|throwable|object<br>
     * [main][text] => string(text|object)<br>
     * [main][title] => string(throwable)<br>
     * [main][trace] => string(throwable)<br>
     * [aux] => null|array<br>
     * [aux][type] => text|throwable|object<br>
     * [aux][text] => string(text|object)<br>
     * [aux][title] => string(throwable)<br>
     * [aux][trace] => string(throwable)<br>
     * [date] => string (09 de Fevereiro de 2019 às 18:32:34)<br>
     * [datetime] => string (2019-02-09 18:32:34)<br>
     * [trace] => null|string<br>
     * [tag] => null|string<br>
     * [url] => string
     *
     * @param string $callback <p>
     * Método que recebera o callback<br>
     * O método deve ser estatico e possuir 1 parâmetro para receber 1 array.<br>
     * Ex: \Namespace\MinhaClasse::Metodo
     * </p>
     * @throws Exception
     */
    public static function addCallback(string $callback) {
        if (is_callable($callback)) {
            self::$callback = $callback;
        } else {
            throw new Exception("Método \"$callback\" passado para a classe Log não existe.");
        }
    }

    /**
     * Salva um log
     *
     * O log será gravado no arquivo *.log.html<br>
     * A gravação do log pode ser desabilitada nas configs do manifest setando "logs" com false
     *
     * @param string $type_log Tipo do log (e, w, i, d)
     * @param string|array|Throwable|object $log A mensagem do Log ou um dado a ser tratado
     * @param string|array|Throwable|object|null $auxiliar <p>
     * Um parametro auxiliar que trata os dados da mesma forma que o parametro $log
     * </p>
     * @param bool $trace Gera uma arvore com os locais que passaram ate chegar aqui
     * @param string $tag Uma tag para o log.
     */
    private static function add(string $type_log, $log, $auxiliar = null, bool $trace = false, string $tag = null) {
        try {
            // Verifica se o callback tentou uma chamada recursiva
            if (self::$callback_run === true) {
                self::$callback_run = false;
                self::$callback_disabled = true;

                throw new Exception("O callback tentou iniciar uma chamada recursiva.", 1);
            }

            $date_time = date("Y-m-d H:i:s");
            $str_trace = "";

            if ($trace) {
                $str_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

                ob_start();
                print_r($str_trace);
                $str_trace = ob_get_clean();
            }

            // Se a msg for uma throwable, gera uma string com os dados.
            $texto = self::gerarTexto($log);
            $texto .= ($auxiliar != null) ? "<br><p>" . self::gerarTexto($auxiliar) : "";
            $texto .= ($str_trace != "") ? "<br><p>" . str_replace("\n", "<br>\n", $str_trace) . "</p>" : "";

            $log_info = [
                "type" => $type_log,
                "main" => self::gerarTexto($log, true),
                "aux" => ($auxiliar != null ? self::gerarTexto($auxiliar, true) : null),
                "date" => self::dataExtenso($date_time),
                "datetime" => $date_time,
                "trace" => $str_trace,
                "tag" => $tag,
                "url" => URL
            ];

            if (CURRENT_ENV == ENV_DEVELOPMENT) {
                // Envia o log para o console de desenvolvimento
                self::logConsole($log_info);
            }

            // Verifica se deve salvar o Log no .log.html
            if (Manifest::getConfig("logs") !== false) self::saveFile($type_log, $texto, $date_time, $tag);

            // Envia o log para um callback
            if (self::$callback !== null && self::$callback_disabled === false && self::$callback_run === false) {
                try {
                    self::$callback_run = true;
                    call_user_func(self::$callback, $log_info);
                    self::$callback_run = false;
                } catch (Throwable|Error|Exception $e) {
                    throw new Exception("Exceção não tratada no callback.", 1);
                }
            }
        } catch (Exception $e) {
            if ($e->getCode() === 1) {
                self::$callback_run = false;
                self::$callback_disabled = true;

                self::e("Ocorreu um erro no callback \"" . self::$callback . "\" de Logs e seu uso foi desativado.", $e, true);
            }
        }
    }

    /**
     * Envia log para o console de depuração
     *
     * @param array $log
     *
     * @return bool
     */
    private static function logConsole(array $log): bool {
        $result = 0;

        try {
            $result = Request::send([
                "url" => Manifest::getConfig("console_url"),
                "method" => Request::GET,
                "data" => $log,
                "timeout" => 2000
            ]);
        } catch (Exception $e) {
        }

        return $result == 1;
    }

    /**
     * Salva um log no arquivo *.log.html
     *
     * @param $type_log
     * @param $msg
     * @param $date_time
     * @param string $tag
     */
    private static function saveFile($type_log, $msg, $date_time, string $tag = null) {
        // Titulo para a mensagem
        $title_color = "color: #CCCCCC;";
        if ($type_log == "d") {
            $type_log = "[Debug]";
            $title_color = "color: #FDAD2B;";
        } else if ($type_log == "e") {
            $type_log = "[Error]";
            $title_color = "color: #FF0000;";
        } else if ($type_log == "w") {
            $type_log = "[Warning]";
            $title_color = "color: #FDE631;";
        } else if ($type_log == "i") {
            $type_log = "[Info]";
            $title_color = "color: #356EFD;";
        }

        $html_tag = ($tag !== null) ? "<span style=\"font-size: 12px;\"> - " . $tag . "</span>" : "";

        // Monta a mensagem com todas as informações passadas
        $msg = "<p><span style=\"font-size: 12px; font-weight: bold; $title_color\">" . str_replace("\n", "<br>\n", $type_log) . "</span>" . $html_tag . "</p>" .
            $msg . "<br>" . URL .
            "<p style=\"font-size: 8px; color: #999999; border-bottom: 1px solid #CCCCCC\">" . self::dataExtenso($date_time) . "</p>\n";

        // Diretorio de saida dos logs
        $log_dir = Files::pathJoin(ROOT, Manifest::getConfig("logs"));
        if (!file_exists($log_dir)) mkdir($log_dir, 0777, true);

        $arquivo = $log_dir . "/" . date("Ymd") . ".log.html";

        Files::write($arquivo, $msg, "append");
    }

    /**
     * Retorna a data informada por extenso
     *
     * @param string $date_time
     *
     * @return string
     */
    private static function dataExtenso(string $date_time): string {
        $ano = date("Y", strtotime($date_time));
        $mes = Text::getOW("helper-date/m" . date("m", strtotime($date_time)));
        $dia = date("d", strtotime($date_time));
        $hora = date("H:i:s", strtotime($date_time));

        return $dia . " de " . $mes . " de " . $ano . " às " . $hora;
    }

    /**
     * Gera um texto para arrays, exceptions, objetos e strings
     *
     * @param string|array|Throwable $log
     * @param bool $in_array Se o retorno deve ser um array
     *
     * @return string|array
     */
    private static function gerarTexto($log, bool $in_array = false) {
        $array_log = [];

        if (is_a($log, 'Throwable')) {
            $log = (object)$log;

            if ($in_array) {
                $array_log['type'] = 'throwable';
                $array_log['title'] = $log->getMessage();
                $array_log['trace'] = $log->getTraceAsString();

                $log = $array_log;
            } else {
                $log = "<p>" .
                    "<b>Throw Message:</b> " . str_replace("\n", "<br>\n", $log->getMessage()) .
                    "<br>\n<br>\n" .
                    "<span style=\"color: #FD0017;\">" . str_replace("\n", "<br>\n", $log->getTraceAsString()) . "</span></p>";
            }
        } else if (is_array($log) || is_object($log)) {
            ob_start();
            print_r($log);
            $obj_string = ob_get_clean();

            if ($in_array) {
                $array_log['type'] = 'object';
                $array_log['text'] = $obj_string;

                $log = $array_log;
            } else {
                $log = '<pre>' . $obj_string . '</pre>';
                $log = "<p>" . str_replace("\n", "<br>\n", $log) . "</p>";
            }
        } else {
            if ($in_array) {
                $array_log['type'] = 'text';
                $array_log['text'] = $log;

                $log = $array_log;
            } else {
                $log = "<p>" . str_replace("\n", "<br>\n", $log) . "</p>";
            }
        }

        return $log;
    }
}