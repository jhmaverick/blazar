<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\System;

use Blazar\Helpers\DateTime;
use Blazar\Helpers\Files;
use Blazar\Helpers\Request;
use Blazar\Manifest;
use Exception;
use Throwable;

/**
 * Controle de logs do sistema
 */
final class Log {
    /**
     * Error Log
     *
     * @see \Blazar\System\Log::e()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $grupo
     */
    public static function e($log, $auxiliar = null, bool $trace = false, string $grupo = null) {
        self::add("e", $log, $auxiliar, $trace, $grupo);
    }

    /**
     * Warning Error
     *
     * @see \Blazar\System\Log::e()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $grupo
     */
    public static function w($log, $auxiliar = null, bool $trace = false, string $grupo = null) {
        self::add("w", $log, $auxiliar, $trace, $grupo);
    }

    /**
     * Info Error
     *
     * @see \Blazar\System\Log::e()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $grupo
     */
    public static function i($log, $auxiliar = null, bool $trace = false, string $grupo = null) {
        self::add("i", $log, $auxiliar, $trace, $grupo);
    }

    /**
     * Debug Log
     *
     * @see \Blazar\System\Log::e()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $grupo
     */
    public static function d($log, $auxiliar = null, bool $trace = false, string $grupo = null) {
        if (CURRENT_ENV == ENV_PRODUCTION)
            self::add("w", "Um log de debug pode ter sido esquecido e entrou em produção.", null, $grupo, true);

        self::add("d", $log, $auxiliar, $trace, $grupo);
    }

    /**
     * Salva um log
     *
     * o log será gravado no arquivo *.log.html
     *
     * @param string $type_log Tipo do log (e, w, i, d)
     * @param string|array|Throwable|object $log A mensagem do Log ou um dado a ser tratado
     * @param string|array|Throwable|object|null $auxiliar <p>
     * Um parametro auxiliar que trata os dados da mesma forma que o parametro $log
     * </p>
     * @param bool $trace Gera uma arvore com os locais que passaram ate chegar aqui
     * @param string $grupo <p>
     * O grupo ao qual o log pertence. É usado apenas quando salvo no banco.
     * </p>
     */
    private static function add(string $type_log, $log, $auxiliar = null, bool $trace = false, string $grupo = null) {
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

        if (CURRENT_ENV == ENV_DEVELOPMENT) {
            $log_console = [
                "type" => $type_log,
                "main" => self::gerarTexto($log, true),
                "aux" => ($auxiliar != null ? self::gerarTexto($auxiliar, true) : null),
                "date" => self::dataExtenso($date_time),
                "trace" => $str_trace,
                "url" => URL
            ];

            // Envia o log para o console de desenvolvimento
            self::logConsole($log_console);
        }

        if (Manifest::getConfig("logs") !== false) self::addTxt($type_log, $texto, $date_time);
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
     */
    private static function addTxt($type_log, $msg, $date_time) {
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

        // Monta a mensagem com todas as informações passadas
        $msg = "<p><span style=\"font-size: 12px; font-weight: bold; $title_color\">" . str_replace("\n", "<br>\n", $type_log) . "</span></p>" .
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
        $mes = DateTime::converteMes(date("m", strtotime($date_time)));
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