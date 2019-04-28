<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\Log;

use Blazar;
use Blazar\Component\FileSystem\FileSystem;
use Blazar\Core\Manifest;
use Error;
use Exception;
use Requests;
use Throwable;

/**
 * Controle de logs do sistema.
 */
class Log {

    /* Essa classe é responsável por receber todos os Logs do sistema, incluindo os de erro, por isto ela
     * não deve ter muitas dependencies de outras classes para evitar o inicio de um loop infinito.
     */

    private const MAX_FILE_SIZE = (1024 * 1024);

    /**
     * O diretório padrão de logs.
     */
    const DEFAULT_DIR = 'logs';

    /**
     * Error Log.
     *
     * @see \Blazar\Core\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     *
     * @return array|null
     */
    public static function e($log, $auxiliar = null, bool $trace = false, string $tag = null): ?array {
        return self::add('e', $log, $auxiliar, $trace, $tag);
    }

    /**
     * Warning Log.
     *
     * @see \Blazar\Core\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     *
     * @return array|null
     */
    public static function w($log, $auxiliar = null, bool $trace = false, string $tag = null): ?array {
        return self::add('w', $log, $auxiliar, $trace, $tag);
    }

    /**
     * Info Log.
     *
     * @see \Blazar\Core\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     *
     * @return array|null
     */
    public static function i($log, $auxiliar = null, bool $trace = false, string $tag = null): ?array {
        return self::add('i', $log, $auxiliar, $trace, $tag);
    }

    /**
     * Debug Log.
     *
     * Esse log deve ser utilizado apenas para debug em desenvolvimento e removido.<br>
     * Logs de debug em produção irão gerar um log warning
     *
     * @see \Blazar\Core\Log::add()
     *
     * @param string|array|Throwable|object $log
     * @param string|array|Throwable|object|null $auxiliar
     * @param bool $trace
     * @param string $tag
     *
     * @return array|null
     */
    public static function d($log, $auxiliar = null, bool $trace = false, string $tag = null): ?array {
        if (defined('CURRENT_ENV') && CURRENT_ENV == Blazar::ENV_PRODUCTION) {
            self::add('w', 'Um log de debug pode ter sido esquecido e entrou em produção.', null, true, $tag);
        }

        return self::add('d', $log, $auxiliar, $trace, $tag);
    }

    /**
     * Salva um log.
     *
     * O log será gravado no arquivo *.log.html<br>
     * A gravação do log pode ser desabilitada nas configs do manifest setando "logs" com false<br>
     * <br>
     * O callback receberá um array como parâmetro com os indices: <br>
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
     * [date] => string (2019-02-09 18:32:34)<br>
     * [trace] => null|string<br>
     * [tag] => null|string<br>
     * [url] => string
     *
     * @param string $type_log Tipo do log (e, w, i, d)
     * @param string|array|Throwable|object $log A mensagem do Log ou um dado a ser tratado
     * @param string|array|Throwable|object|null $auxiliar <p>
     * Um parametro auxiliar que trata os dados da mesma forma que o parametro $log
     * </p>
     * @param bool $trace Gera uma arvore com os locais que passaram ate chegar aqui
     * @param string $tag Uma tag para o log.
     *
     * @return array|null Retorna null caso o log não consiga ser salvo.
     */
    private static function add(string $type_log, $log, $auxiliar = null, bool $trace = false, string $tag = null): ?array {
        try {
            // Se a msg for uma throwable, gera uma string com os dados.
            if ($log === null) {
                $log = 'null';
            }

            $date_time = date('Y-m-d H:i:s');
            $str_trace = '';

            if ($trace) {
                $ls_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

                // Verifica se a chamada foi repassada por um dos métodos intermediarios
                if (isset($ls_trace[0]) && $ls_trace[0]['class'] == __CLASS__ && $ls_trace[0]['function'] == 'add' &&
                    isset($ls_trace[1]) && $ls_trace[1]['class'] == __CLASS__ && in_array($ls_trace[1]['function'], ['e', 'w', 'i', 'd'])) {
                    array_shift($ls_trace);
                }

                $str_trace = self::strTrace($ls_trace);
            }

            $log_info = [
                'type' => $type_log,
                'main' => self::formatText($log, true),
                'aux' => ($auxiliar != null ? self::formatText($auxiliar, true) : null),
                'date' => $date_time,
                'trace' => $str_trace,
                'tag' => $tag,
                'url' => URL,
            ];

            // Envia o log para o console de desenvolvimento
            if (defined('CURRENT_ENV') && CURRENT_ENV == Blazar::ENV_DEVELOPMENT) {
                self::logConsole($log_info);
            }

            // Verifica se deve salvar o log em html
            $format = Manifest::config('save_logs');
            if ($format == 'html' || $format == 'all') {
                $texto = self::formatText($log);
                $texto .= ($auxiliar != null) ? '<br><p>' . self::formatText($auxiliar) : '';
                $texto .= ($str_trace != '') ? '<br><p>' . str_replace("\n", "<br>\n", $str_trace) . '</p>' : '';

                self::saveHTML($type_log, $texto, $date_time, $tag);
            }

            // Verifica se deve salvar o log em json
            if ($format == 'json' || $format == 'all' || $format === null) {
                self::saveJSON($log_info);
            }

            return $log_info;
        } catch (Error|Throwable $e) {
            // Se catch tenta capturar todas as possíveis exceções
            if (defined('CURRENT_ENV') && CURRENT_ENV == Blazar::ENV_DEVELOPMENT) {
                echo "<pre>\n== Erro ao adicionar Log =====================\n\n";
                print_r($e);
                echo "\n</pre>\n\n";
            }

            return null;
        }
    }

    /**
     * Monta uma exibição amigavel para a trace.
     *
     * @param array $trace
     *
     * @return string
     */
    private static function strTrace(array $trace): string {
        $final_trace = [];
        $i = 0;

        foreach ($trace as $v) {
            $file = isset($v['file']) ? $v['file'] : '';
            $line = isset($v['line']) ? $v['line'] : '';
            $class = isset($v['class']) ? $v['class'] : '';
            $type = isset($v['type']) ? $v['type'] : '';
            $function = isset($v['function']) ? $v['function'] : '';
            $args = [];

            if (isset($v['args'])) {
                foreach ($v['args'] as $value) {
                    $arg = substr($value, 0, 15);
                    $args[] = "'" . $arg . (strlen($value) > 15 ? '...' : '') . "'";
                }
            }

            $args = implode(', ', $args);

            $final_trace[] = "#$i " . $file . '(' . $line . '): ' . $class . $type . $function . "($args)";
            $i++;
        }

        $final_trace[] = "#$i {main}";

        return implode("\n", $final_trace);
    }

    /**
     * Gera um texto para arrays, exceptions, objetos e strings.
     *
     * @param string|array|Throwable $log
     * @param bool $in_array Se o retorno deve ser um array
     *
     * @return string|array
     */
    private static function formatText($log, bool $in_array = false) {
        $array_log = [];

        if (is_a($log, 'Throwable')) {
            $log = (object) $log;

            if ($in_array) {
                $array_log['type'] = 'throwable';
                $array_log['title'] = $log->getMessage();
                $array_log['trace'] = $log->getTraceAsString();

                $log = $array_log;
            } else {
                $log = '<p>' .
                    '<b>Throw Message:</b> ' . str_replace("\n", "<br>\n", $log->getMessage()) .
                    "<br>\n<br>\n" .
                    '<span style="color: #FD0017;">' . str_replace("\n", "<br>\n", $log->getTraceAsString()) . '</span></p>';
            }
        } elseif (is_array($log) || is_object($log)) {
            $obj_string = print_r($log, true);

            if ($in_array) {
                $array_log['type'] = 'object';
                $array_log['text'] = $obj_string;

                $log = $array_log;
            } else {
                $log = '<pre>' . $obj_string . '</pre>';
                $log = '<p>' . str_replace("\n", "<br>\n", $log) . '</p>';
            }
        } else {
            if ($in_array) {
                $array_log['type'] = 'text';
                $array_log['text'] = $log;

                $log = $array_log;
            } else {
                $log = '<p>' . str_replace("\n", "<br>\n", $log) . '</p>';
            }
        }

        return $log;
    }

    /**
     * Envia log para o console de depuração.
     *
     * @param array $log
     *
     * @return bool
     */
    private static function logConsole(array $log): bool {
        $result = 0;

        try {
            $console_url = Manifest::config('console_url');

            if ($console_url !== null) {
                $url = $console_url . '?' . http_build_query($log);
                Requests::get($url, [], ['timeout' => 2000]);
            }
        } catch (Exception $e) {
        }

        return $result == 1;
    }

    /**
     * Salva o log em um arquivo *.log.html.
     *
     * @param $type_log
     * @param $msg
     * @param $date_time
     * @param string $tag
     *
     * @return bool
     */
    private static function saveHTML($type_log, $msg, $date_time, string $tag = null): bool {
        $log_dir = self::getDirLogs();
        $arquivo = $log_dir . '/' . date('Y-m-d') . '.log.html';

        if (file_exists($arquivo) && filesize($arquivo) > self::MAX_FILE_SIZE) {
            return false;
        }

        // Titulo para a mensagem
        $title_color = 'color: #CCCCCC;';
        if ($type_log == 'd') {
            $type_log = '[Debug]';
            $title_color = 'color: #FDAD2B;';
        } elseif ($type_log == 'e') {
            $type_log = '[Error]';
            $title_color = 'color: #FF0000;';
        } elseif ($type_log == 'w') {
            $type_log = '[Warning]';
            $title_color = 'color: #FDE631;';
        } elseif ($type_log == 'i') {
            $type_log = '[Info]';
            $title_color = 'color: #356EFD;';
        }

        $html_tag = ($tag !== null) ? '<span style="font-size: 12px;"> - ' . $tag . '</span>' : '';

        // Monta a mensagem com todas as informações passadas
        $msg = "<p><span style=\"font-size: 12px; font-weight: bold; $title_color\">" . str_replace("\n", "<br>\n", $type_log) . '</span>' . $html_tag . '</p>' .
            $msg . '<br>' . URL .
            '<p style="font-size: 8px; color: #999999; border-bottom: 1px solid #CCCCCC">' . date("d/m/Y à\s H:i:s", strtotime($date_time)) . "</p>\n";

        file_put_contents($arquivo, $msg, FILE_APPEND);

        return true;
    }

    /**
     * Salva o log em um arquivo *.log.json.
     *
     * @param array $log_info
     *
     * @return bool
     */
    private static function saveJSON(array $log_info): bool {
        $log_dir = self::getDirLogs();
        $arquivo = $log_dir . '/' . date('Y-m-d') . '.log.json';

        if (file_exists($arquivo) && filesize($arquivo) > self::MAX_FILE_SIZE) {
            return false;
        }

        $logs = [];

        // Verifica se o arquivo já existe
        $str_json = (file_exists($arquivo)) ? @file_get_contents($arquivo) : false;
        if ($str_json != false) {
            $logs = json_decode($str_json, true) ?? [];
        }

        $logs[] = $log_info;

        // Salva o log com o novo index
        $logs_str = json_encode($logs, JSON_PRETTY_PRINT);
        file_put_contents($arquivo, $logs_str);

        return true;
    }

    /**
     * Retorna o diretório dos logs.
     *
     * Se o diretório não existir ele é criado
     *
     * @return string
     */
    private static function getDirLogs(): string {
        // Diretório de saída dos logs
        $log_dir = Manifest::config('logs_dir') ?? self::DEFAULT_DIR;
        $log_dir = trim($log_dir);

        $log_dir = FileSystem::pathResolve(APP_ROOT, $log_dir);

        $log_dir = FileSystem::pathJoin($log_dir);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }

        return $log_dir;
    }
}