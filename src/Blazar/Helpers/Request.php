<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Helpers;

use Exception;

/**
 * Responsavel por pegar e enviar requisições _POST e _GET
 */
final class Request {
    const POST = 1;
    const GET = 2;

    /**
     * Obtem post e get em uma unica requisição
     *
     * Se existir o mesmo index no get e no post, o valor do get sobressai
     *
     * @param bool $acao - Separar a ação da requisição
     *
     * @return array
     */
    public static function get($acao = false) {
        $request = array("acao" => null, "dados" => array());

        // Pega os valores do _POST
        foreach ($_POST as $index => $valor) {
            // Define a ação
            if ($acao && $index == "acao") {
                $request["acao"] = $_POST['acao'];
            } else {
                $request["dados"][$index] = $valor;
            }
        }

        // Pega os valores do _GET
        foreach ($_GET as $index => $valor) {
            // Define a ação
            if ($acao && $index == "acao") {
                $request["acao"] = $_GET['acao'];
            } else {
                $request["dados"][$index] = $valor;
            }
        }

        // Passa o index dados para o array principal
        if (!$acao) {
            $request = $request["dados"];
        }

        return $request;
    }

    /**
     * Enviar Requisição
     *
     * @param array|string $dados <p>
     * Se este parametro for um array, os outros parametros serão ignorados e o indice url deve ser passado.
     * Se for uma string, este parametro será a URL.
     * </p>
     * @param int $method self::POST ou self::GET
     * @param array $data Parâmetros para envio
     * @param bool $send_json Enviar os parâmetros como um JSON
     * @param null $timeout Tempo de execução
     * @return string
     * @throws Exception
     */
    public static function send($dados, int $method = self::POST, array $data = [], bool $send_json = false, $timeout = null) {
        if (is_array($dados)) {
            if (!isset($dados['url']))
                throw new Exception("A url é obrigatoria para Requisição");

            $url = $dados['url'];
            $method = $dados['method'] ?? self::POST;
            $data = $dados['data'] ?? [];
            $send_json = $dados['send_json'] ?? false;
            $timeout = $dados['timeout'] ?? null;
        } else {
            $url = $dados;
        }

        $ch = curl_init();

        if ($method == self::GET && count($data) > 0) {
            // Verifica se ja existe um get na URL
            $char = (substr_count($url, "?") == 0) ? "?" : "&";
            $url = $url . $char . http_build_query($data);

            if ($send_json) $data = json_encode($data);
        } else if ($method == self::POST) {
            if ($send_json) $data = json_encode($data);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($timeout !== null)
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);

        if ($send_json)
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ]
            );

        $result = curl_exec($ch);

        curl_close($ch);

        return trim($result);
    }

    public static function getallheaders() {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }
}