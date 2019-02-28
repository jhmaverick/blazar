<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source
 * code.
 */

namespace Blazar\Core;

use Blazar\Component\FileSystem\FileSystem;
use Blazar\Component\TypeRes\StrRes;
use Error;
use Throwable;

/**
 * Classe de gerenciamento do blazar-manifest.json
 */
class Manifest extends App {

    private static $started = false;
    private static $config = [
        "force_https" => false,
        "force_www" => 0,
        "max_img_width" => 1920,
        "max_img_height" => 1080,
        "max_upload_filesize" => "10MB",
        "logs" => "logs",
        "cors" => null,
        "env" => ENV_PRODUCTION,
        "console_url" => "http://localhost:4000"
    ];
    private static $data = [];
    private static $dbs = [];
    private static $map = [];

    /**
     * Inicia a configuração do sistema com os dados do manifest
     * TODO Gerar um arquivo serialise dos dados carregados para não ser necessario esse processamento todas as vezes
     * O codigo do arquivo deve ser gerado com base no conteúdo do json e só ser recriado caso o conteudo mude
     * @throws BlazarException
     */
    public function __construct() {
        // Impede que a função seja iniciada mais de uma vez
        if (self::$started) throw new BlazarException("Metodo \Blazar\Manifest::prepare foi chamado novamente.");
        self::$started = true;

        try {
            // Gera os parâmetros da url
            self::extractUrlParams();

            // TODO Validar manifest por schema para garantir que apenas informações validas serão adicionadas

            $manifest_path = (defined("MANIFEST_PATH")) ? MANIFEST_PATH : APP_ROOT . "/blazar-manifest.json";

            if (file_exists($manifest_path)) {
                $dados_manifest = self::readManifestFile($manifest_path);

                // Verifica se existe um manifest para mesclar com o principal
                $custom_manifest = (defined("CUSTOM_MANIFEST")) ? CUSTOM_MANIFEST : APP_ROOT . "/custom-manifest.json";

                if (file_exists($custom_manifest)) {
                    $custom = self::readManifestFile($custom_manifest);
                    $dados_manifest = array_replace_recursive($dados_manifest, $custom);
                }

                // Mescla Configurações do manifest com as padrões
                if (isset($dados_manifest['configs'])) self::$config = array_merge(self::$config, $dados_manifest['configs']);

                // Aplica configurações
                self::applyConfigs();

                // Bancos de dados
                if (isset($dados_manifest['dbs'])) {
                    foreach ($dados_manifest['dbs'] as $index => $value) {
                        self::$dbs[$index] = $value;
                    }
                }

                // Pega dados do aplicativo
                if (isset($dados_manifest['data'])) {
                    foreach ($dados_manifest['data'] as $index => $value) {
                        self::$data[$index] = $value;
                    }
                }

                // Verifica se existe uma aplicação para o sistema iniciar
                if (isset($dados_manifest['map']) && is_array($dados_manifest['map']) && count($dados_manifest['map']) > 0) {
                    self::$map = $dados_manifest['map'];

                    // Reajusta os index da url com os padrões
                    self::$max_index_map = self::preencherParametro(0, $dados_manifest['map']);
                }
            }
        } catch (Error|Throwable $e) {
            Log::e($e);
            exit("Erro ao iniciar o sistema.");
        }
    }

    /**
     * Pega configurações o indice configurações
     *
     * @param string|null $index Nome do indice desejado ou null para retornar um array com todos.
     *
     * @return mixed
     */
    public static function config(string $index = null) {
        if ($index !== null) {
            return self::$config[$index] ?? null;
        } else {
            return self::$config;
        }
    }

    /**
     * Pega dados e informações da aplicação
     *
     * @param string|null $index Nome do indice desejado ou null para retornar um array com todos.
     *
     * @return mixed
     */
    public static function data(string $index = null) {
        if ($index !== null) {
            return self::$data[$index] ?? null;
        } else {
            return self::$data;
        }
    }

    /**
     * Pega os dados de um Banco
     *
     * @param string|null $connection_name Nome da conexão desejada ou null para retornar um array com todas.
     *
     * @return array
     */
    public static function db(string $connection_name = null): array {
        if ($connection_name !== null) {
            return self::$dbs[$connection_name] ?? null;
        } else {
            return self::$dbs;
        }
    }

    /**
     * Retorna o mapa de classes definido no manifest.
     *
     * @param string|null $route <p>
     * Se for informado irá percorrer o array ate completar a rota informada.<br>
     * Ex: nivel1/nivel2/nivel3
     * </p>
     * @param string|null $index Pega um indice no final da rota encontrada
     *
     * @return array|mixed|null Retorna null caso uma rota tenha sido informada e não exista no manifest.
     */
    public static function map(string $route = null, string $index = null) {
        // Percorre a rota informada
        if ($route !== null) {
            $arvore = explode("/", $route);

            $final = ["sub" => self::$map];

            for ($i = 0; $i < count($arvore); $i++) {
                $atual = $arvore[$i];

                if (!isset($final["sub"]) || !isset($final["sub"][$atual])) {
                    return null;
                }

                $final = $final["sub"][$atual];
            }

            if ($index !== null) return $final[$index] ?? null;
            return $final;
        } else {
            // Retorna todos os parâmetros
            return self::$map;
        }
    }

    /**
     * Gera um array com parâmetros da url
     *
     * @throws BlazarException
     */
    private static function extractUrlParams() {
        $url = [];

        $un_get = explode("?", URL);
        $url_completa = $un_get[0];

        // Pega parâmetros da URL
        if ($url_completa != URL_BASE) {
            // remove caminho raiz da página
            $p_atual = explode(URL_BASE, $url_completa);

            if (isset($p_atual[1])) {
                $p_atual = $p_atual[1];

                // corta url se ela tiver mais de 1 parâmetro
                if (substr_count($p_atual, '/') != 0) {
                    $url = explode('/', $p_atual);

                    // Evita que barras no final da url sejam interpretadas com um parâmetro
                    if ($url[count($url) - 1] == "") unset($url[count($url) - 1]);
                } else {
                    $url[0] = $p_atual;
                }
            } else {
                throw new BlazarException("Problemas ao extrair os parâmetros da url.\n" .
                    "A constante BASE deve ser o inicio da constante URL.\n" .
                    "URL: " . URL . "\n" .
                    "BASE: " . URL_BASE);
            }
        }

        self::$parameters = self::$url_params = $url;
    }

    /**
     * Faz a leitura do arquivo e retorna em array
     *
     * @param string $local
     *
     * @return array
     * @throws BlazarException
     */
    private static function readManifestFile(string $local): array {
        $file_name = explode("/", $local);
        $file_name = end($file_name);

        $dados_manifest = FileSystem::read($local);

        if ($dados_manifest === null) {
            throw new BlazarException("Manifest: O arquivo \"$file_name\" não foi encontrado.");
        }

        // Remove comentarios do JSON
        $dados_manifest = StrRes::removeComments($dados_manifest);

        // Verifica inclusões de arquivos
        preg_match_all('~[\"|\']include>>(.+?)[\"|\']~', $dados_manifest, $retorno);

        // Aplica o que foi encontrado nos includes no json principal
        foreach ($retorno[0] as $index => $value) {
            if (file_exists(APP_ROOT . "/" . $retorno[1][$index])) {
                $conteudo_json = file_get_contents(APP_ROOT . "/" . $retorno[1][$index]);

                if (json5_decode($conteudo_json, true) != null) {
                    $dados_manifest = StrRes::replaceFirst($dados_manifest, $retorno[0][$index], $conteudo_json);
                } else {
                    throw new BlazarException("O código encontrado não é um JSON.\n" .
                        "arquivo: " . htmlspecialchars($retorno[0][$index]) . "\n" .
                        "declaração: " . $retorno[1][$index]);
                }
            } else {
                throw new BlazarException("O arquivo não pode ser incluido no manifest.\n" .
                    "arquivo: " . htmlspecialchars($retorno[0][$index]) . "\n" .
                    "declaração: " . $retorno[1][$index]);
            }
        }

        if (json5_decode($dados_manifest, true)) {
            $dados_manifest = json5_decode($dados_manifest, true);
        } else {
            throw new BlazarException("Manifest: O arquivo \"$file_name\" não é um JSON.");
        }

        return $dados_manifest;
    }

    /**
     * Aplica algumas configurações setadas.
     */
    private static function applyConfigs() {
        // Define ambiente
        if (!defined("CURRENT_ENV")) {
            /**
             * Ambiente onde o sistema esta rodando.
             *
             * ENV_DEVELOPMENT, ENV_TESTING ou ENV_PRODUCTION.
             */
            define("CURRENT_ENV", Manifest::config("env"));
        }

        // Se o índice logs estiver como true altera para o padrão
        if (self::$config["logs"] == true) self::$config["logs"] = "logs";

        // Redirecionar para https
        if (CURRENT_ENV == ENV_PRODUCTION && self::config("force_https") && !isset($_SERVER['HTTPS'])) {
            header("location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
            exit();
        }

        // Força redirecionamento para url com "www."
        if (CURRENT_ENV == ENV_PRODUCTION &&
            self::config("force_www") == 1 &&
            substr_count($_SERVER['SERVER_NAME'], 'www.') == 0
        ) {
            header("location: //www." . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit();
        } // Força redirecionamento para url sem "www."
        else if (CURRENT_ENV == ENV_PRODUCTION &&
            self::config("force_www") == -1 &&
            substr_count($_SERVER['SERVER_NAME'], 'www.') != 0
        ) {
            header("location: //" . substr($_SERVER['HTTP_HOST'], 4) . $_SERVER['REQUEST_URI']);
            exit();
        }

        // Controle de Cross Origin
        if (($cors = self::config("cors")) !== null) {
            // Verifica se é uma requisição cross origin e se ela esta liberada
            if (isset($_SERVER['HTTP_ORIGIN']) &&
                ((is_string($cors) && (trim($cors) === "*" || trim($cors) === $_SERVER['HTTP_ORIGIN'])) ||
                    (is_array($cors) && in_array($_SERVER['HTTP_ORIGIN'], $cors)))
            ) {
                // should do a check here to match $_SERVER['HTTP_ORIGIN'] to a whitelist of safe domains
                header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400'); // cache for 1 day

                // Access-Control headers are received during OPTIONS requests
                if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

                    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
                }
            }
        }
    }

    /**
     * Preenche os parâmetros vazios
     *
     * @param int $index indice do parâmetro
     * @param array $map_list Mapa de parâmetro do manifest
     *
     * @return mixed
     * @throws BlazarException
     */
    private static function preencherParametro(int $index, array $map_list) {
        // O primeiro indice será o main
        reset($map_list);
        $main = key($map_list);

        // Verifica conflitos de apps principais
        if ($main == null) {
            throw new BlazarException("Nenhum parâmetro foi definido como principal no index \"" . key($map_list) . "\".");
        }

        // Verifica se o index da url é um parâmetro do sistema
        if (!isset($map_list[App::param($index)])) {
            $url = App::param();

            $new_url = [];
            $new_url[0] = array_slice($url, 0, $index);
            $new_url[1] = array_slice($url, $index);
            array_unshift($new_url[1], $main);

            $url = array_merge($new_url[0], $new_url[1]);
            self::$parameters = $url;
        }

        self::$map_params[] = self::paramInfo($index, $map_list[App::param($index)]);

        // Verifica proximo index
        if (isset($map_list[App::param($index)]['sub'])) {
            $index = self::preencherParametro($index + 1, $map_list[App::param($index)]['sub']);
        }

        return $index;
    }

    /**
     * Completa as informações do parâmetro
     *
     * @param string $index Indice do parâmetro
     * @param array $param_info Dados do parâmetro
     *
     * @return array
     * @throws BlazarException
     */
    private static function paramInfo(string $index, array $param_info): array {
        if (isset($param_info['sub'])) unset($param_info['sub']);

        $param_info['name'] = App::param($index);
        $param_info['index'] = $index;

        if (!class_exists($param_info['class']))
            throw new BlazarException("A Classe \"" . $param_info['class'] . "\" informada no map não existe.");

        $params_map = App::get();
        $params_full = App::param(null, App::PARAMS_ALL);

        // Gera a rota do parâmetro na url
        $route = [];
        for ($i = 0; $i < count($params_map) + 1; $i++) {
            $route[] = $params_full[$i];
        }

        $param_info["route"] = implode("/", $route);
        $param_info["url_path"] = URL_BASE . $param_info["route"];

        return $param_info;
    }

}