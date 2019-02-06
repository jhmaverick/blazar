<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar;

use Blazar\Helpers\Files;
use Blazar\Helpers\StrRes;
use Blazar\System\Log;
use Error;
use Exception;

/**
 * Classe de gerenciamento do manifest.json
 */
class Manifest extends Application {

    const MANIFEST_LOCAL = "manifest.json";

    private static $manifest = [];
    private static $config = [
        "force_https" => false,
        "force_www" => 0,
        "max_img_width" => 1920,
        "max_img_height" => 1080,
        "max_upload_filesize" => "10MB",
        "logs" => "logs",
        "cors" => null,
        "console_url" => "http://localhost:4000"
    ];
    private static $data = [];
    private static $dbs = [];
    private static $map = [];

    /**
     * Inicia a configuração do sistema com os dados do manifest
     */
    public function __construct() {
        // Evita que este metodo seja chamado 2 vezes
        if (count(self::$manifest) > 0) return;

        try {
            // Gera os parametros da url
            self::gerarUrl();
        } catch (ManifestException $e) {
            Log::e("Main, Gerando parametros da url", $e, "manifest");
        }

        try {
            if (file_exists(ROOT . "/" . self::MANIFEST_LOCAL)) {
                $dados_manifest = self::readManifestFile(ROOT . "/" . self::MANIFEST_LOCAL);

                // Verifica se existe um manifest alterado para o ambiente
                if (CURRENT_ENV !== ENV_PRODUCTION &&
                    getenv('CUSTOM_MANIFEST') !== false &&
                    file_exists(getenv('CUSTOM_MANIFEST'))
                ) {
                    $custom = self::readManifestFile(ROOT . "/" . getenv('CUSTOM_MANIFEST'));
                    $dados_manifest = array_replace_recursive($dados_manifest, $custom);
                }

                self::$manifest = $dados_manifest;

                // Configurações
                if (isset($dados_manifest['configs'])) {
                    foreach ($dados_manifest['configs'] as $index => $value) {
                        // Se o indice logs estiver como true altera para o padrão
                        if ($index == "logs" && $value === true) $value = self::$config[$index];

                        self::$config[$index] = $value;
                    }
                }

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
                    $system_param = self::preencherParametro(0, $dados_manifest['map']);

                    // Cria um array com as urls que não são usadas diretamente pelo sistema
                    Application::setFinalIndexSystem($system_param);
                }
            }
        } catch (Exception $e) {
            Log::e($e);
            exit("Erro ao iniciar o sistema.");
        } catch (Error $e) {
            Log::e($e);
            exit("Erro ao iniciar o sistema.");
        }
    }

    /**
     * Faz a leitura do arquivo e retorna em array
     *
     * @param string $local
     *
     * @return array
     * @throws ManifestException
     */
    private static function readManifestFile(string $local): array {
        $file_name = explode("/", $local);
        $file_name = end($file_name);

        $dados_manifest = Files::read($local);

        if ($dados_manifest === null) {
            throw new ManifestException("Manifest: O arquivo \"$file_name\" não foi encontrado.");
        }

        // Remove comentarios do JSON
        $dados_manifest = StrRes::removeComments($dados_manifest);

        // Verifica inclusões de arquivos
        preg_match_all('~[\"|\']include>>(.+?)[\"|\']~', $dados_manifest, $retorno);

        // Aplica o que foi encontrado nos includes no json principal
        foreach ($retorno[0] as $index => $value) {
            if (file_exists(ROOT . "/" . $retorno[1][$index])) {
                $conteudo_json = file_get_contents(ROOT . "/" . $retorno[1][$index]);

                if (json_decode($conteudo_json, true) != null) {
                    $dados_manifest = StrRes::str_freplace($retorno[0][$index], $conteudo_json, $dados_manifest);
                } else {
                    throw new ManifestException("O código encontrado não é um JSON.\n" .
                        "arquivo: " . htmlspecialchars($retorno[0][$index]) . "\n" .
                        "declaração: " . $retorno[1][$index]);
                }
            } else {
                throw new ManifestException("O arquivo não pode ser incluido no manifest.\n" .
                    "arquivo: " . htmlspecialchars($retorno[0][$index]) . "\n" .
                    "declaração: " . $retorno[1][$index]);
            }
        }

        if (json_decode($dados_manifest, true)) {
            $dados_manifest = json_decode($dados_manifest, true);
        } else {
            throw new ManifestException("Manifest: O arquivo \"$file_name\" não é um JSON.");
        }

        return $dados_manifest;
    }

    /**
     * Retorna o manifesto completo em array e sem tratamentos
     *
     * @return array
     */
    public static function getManifest(): array {
        return self::$manifest;
    }

    /**
     * Pega configurações para o funcionamento do sistema
     *
     * @param $index
     *
     * @return mixed
     */
    public static function getConfig(string $index) {
        if ($index !== null) {
            return self::$config[$index] ?? null;
        } else {
            return self::$config;
        }
    }

    /**
     * Pega dados e informações da aplicação
     *
     * @param $index
     *
     * @return mixed
     */
    public static function getData($index = null) {
        if ($index !== null) {
            return self::$data[$index] ?? null;
        } else {
            return self::$data;
        }
    }

    /**
     * Dados do banco
     *
     * @param string $index
     *
     * @return array (host, user, pass, db)
     */
    public static function getDB(string $index): array {
        return self::$dbs[$index];
    }

    /**
     * Retorna o mapa de classes do manifest
     *
     * @return array
     */
    public static function getMap(): array {
        return self::$map;
    }

    /**
     * Preenche os parametros vazios
     *
     * @param int $index indice do parametro
     * @param array $map_list Mapa de parametro do manifest
     *
     * @return mixed
     * @throws ManifestException
     */
    private static function preencherParametro(int $index, array $map_list) {
        $main = null;
        // Pega o app principal do index
        foreach ($map_list as $nome => $value) {
            if (isset($value['main']) && $value['main'] === true) {
                if ($main == null) {
                    $main = $nome;
                } else {
                    throw new ManifestException("Existe mais de 1 parâmetro definido como principal.");
                }
            }
        }

        // Verifica conflitos de apps principais
        if ($main == null) {
            throw new ManifestException("Nenhum parâmetro foi definido como principal no index \"" . key($map_list) . "\".");
        }

        // Verifica se o index da url é um parametro do sistema
        if (!isset($map_list[Application::getParameter($index)])) {
            $url = Application::getParameter();

            $new_url = array();
            $new_url[0] = array_slice($url, 0, $index);
            $new_url[1] = array_slice($url, $index);
            array_unshift($new_url[1], $main);

            $url = array_merge($new_url[0], $new_url[1]);
            Application::setUrlParameters($url);
        }

        self::addInParameterInfo($index, $map_list[Application::getParameter($index)]);

        // Verifica proximo index
        if (isset($map_list[Application::getParameter($index)]['sub'])) {
            $index = self::preencherParametro($index + 1, $map_list[Application::getParameter($index)]['sub']);
        }

        return $index;
    }

    /**
     * Adiciona o parametro na array
     *
     * @param string $name Nome do parametro
     * @param array $param_info Dados do parametro
     *
     * @throws ManifestException
     */
    private static function addInParameterInfo(string $name, array $param_info) {
        $param_info['name'] = $name;

        if (isset($map_tree['sub'])) $param_info['sub'] = [];

        if (!class_exists($param_info['class']))
            throw new ManifestException("A Classe \"" . $param_info['class'] . "\" informada no map não existe.");

        $params_map = Application::getParameterInfo();
        $params_full = Application::getParameter(null, Application::PARAMS_FULL);

        // Gera a rota do parametro na url
        $current_url = [];
        for ($i = 0; $i < count($params_map) + 1; $i++) {
            $current_url[] = $params_full[$i];
        }

        $param_info["url_path"] = BASE . implode("/", $current_url);

        Application::addParametersTree($param_info);
    }

    /**
     * Tratamento de https e www
     */
    private static function applyConfigs() {
        // Redirecionar para https
        if (CURRENT_ENV == ENV_PRODUCTION && self::getConfig("force_https") && !isset($_SERVER['HTTPS'])) {
            header("location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
            exit();
        }

        // Força redirecionamento para url com "www."
        if (CURRENT_ENV == ENV_PRODUCTION &&
            self::getConfig("force_www") == 1 &&
            substr_count($_SERVER['SERVER_NAME'], 'www.') == 0
        ) {
            header("location: //www." . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit();
        } // Força redirecionamento para url sem "www."
        else if (CURRENT_ENV == ENV_PRODUCTION &&
            self::getConfig("force_www") == -1 &&
            substr_count($_SERVER['SERVER_NAME'], 'www.') != 0
        ) {
            header("location: //" . substr($_SERVER['HTTP_HOST'], 4) . $_SERVER['REQUEST_URI']);
            exit();
        }

        // Controle de Cross Origin
        if (($cors = self::getConfig("cors")) !== null) {
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
     * Gera um array com parametros da url
     * @throws ManifestException
     */
    private static function gerarUrl() {
        $url = array();

        $un_get = explode("?", URL);
        $url_completa = $un_get[0];

        // Pega parametros da URL
        if ($url_completa != BASE) {
            // remove caminho raiz da página
            $p_atual = explode(BASE, $url_completa);

            if (isset($p_atual[1])) {
                $p_atual = $p_atual[1];

                // corta url se ela tiver mais de 1 parametro
                if (substr_count($p_atual, '/') != 0) {
                    $url = explode('/', $p_atual);

                    // Evita que barras no final da url sejam interpretadas com um parametro
                    if ($url[count($url) - 1] == "") unset($url[count($url) - 1]);
                } else {
                    $url[0] = $p_atual;
                }
            } else {
                throw new ManifestException("Problemas ao gerar url.");
            }
        }

        Application::setUrlParamsPreManifest($url);
        Application::setUrlParameters($url);
    }
}