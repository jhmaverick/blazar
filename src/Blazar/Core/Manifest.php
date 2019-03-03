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
use Blazar\Component\Text\Text;
use Blazar\Component\TypeRes\ArrayRes;
use Blazar\Component\TypeRes\StrRes;
use Blazar\Component\View\View;
use Error;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use stdClass;
use Throwable;

/**
 * Classe de gerenciamento do blazar-manifest.json
 */
class Manifest extends App {

    const SCHEMA_PATH = BLAZAR_DIR . '/schema/blazar-schema.json';
    const RECORDS_DIR = SOURCE_DIR . '/.records';

    private static $started = false;
    private static $config = [];
    private static $data = [];
    private static $dbs = [];
    private static $map = [];

    /**
     * Inicia a configuração do sistema com os dados do manifest
     */
    public function __construct() {
        // Impede que a função seja iniciada mais de uma vez
        if (self::$started) return;
        self::$started = true;

        try {
            // Gera os parâmetros da url
            self::extractUrlParams();

            // Informações dos arquivos do manifesto
            $files = self::filesInfo();

            // Nome padrão para ser usado caso não exista um arquivo manifeste
            $serialize_name = "default";

            if ($files->main->exists) $serialize_name = $files->main->change_time;
            if ($files->custom->exists) $serialize_name = ($files->main->exists ? $serialize_name . "_" : "") . $files->custom->change_time;

            $serialize_name = "mf_" . md5($serialize_name);

            // Se os dados do manifest não tiverem sido alterados utiliza eles para evitar validações e processamento
            $serialize_file = self::RECORDS_DIR . "/" . $serialize_name;

            if (file_exists($serialize_file)) {
                $dados_manifest = unserialize(file_get_contents($serialize_file));
            } else {
                // Objeto do manifest
                $dados_manifest = [];

                // Manifesto principal
                if ($files->main->exists) $dados_manifest = self::readManifestFile($files->main->path);

                // Manifesto customizado
                if ($files->custom->exists) {
                    $custom = self::readManifestFile($files->custom->path);
                    if ($files->main->exists) $dados_manifest = array_replace_recursive($dados_manifest, $custom);
                }

                // Tratar os dados de acordo com schema
                self::validateSchema($dados_manifest);

                $serialized = serialize($dados_manifest);

                // Remove arquivos de versões antigas
                $files = glob(self::RECORDS_DIR . '/mf_*');
                foreach ($files as $file) {
                    if (is_file($file)) unlink($file);
                }

                // Salva arquivo com serialize
                mkdir(self::RECORDS_DIR, 0777, true);
                file_put_contents($serialize_file, $serialized);
            }

            // Pega as configurações do manifest
            self::$config = $dados_manifest['configs'];

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
        } catch (Error|Throwable $e) {
            Log::e($e);
            exit("Manifest: Erro ao iniciar o sistema.");
        }
    }

    /**
     * Pega configurações o índice configurações
     *
     * @param string|null $index Nome do índice desejado ou null para retornar um array com todos.
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
     * Pega informações dos arquivos do manifest
     *
     * @return stdClass
     */
    private static function filesInfo(): stdClass {
        $manifest_path = (defined("MANIFEST_PATH"))
            ? FileSystem::pathResolve(SOURCE_DIR, MANIFEST_PATH)
            : SOURCE_DIR . "/blazar-manifest.json";

        $main = (object)[
            "exists" => false,
            "path" => $manifest_path,
            "change_time" => null
        ];

        if (file_exists($manifest_path)) {
            $main->exists = true;
            $main->change_time = filectime($manifest_path);
        }

        // Verifica se existe um manifest para mesclar com o principal
        $custom_manifest = (defined("CUSTOM_MANIFEST"))
            ? FileSystem::pathResolve(SOURCE_DIR, CUSTOM_MANIFEST)
            : SOURCE_DIR . "/custom-manifest.json";

        $custom = (object)[
            "exists" => false,
            "path" => $custom_manifest,
            "change_time" => null
        ];

        if (file_exists($custom_manifest)) {
            $custom->exists = true;
            $custom->change_time = filectime($custom_manifest);
        }

        return (object)[
            "main" => $main,
            "custom" => $custom
        ];
    }

    /**
     * Valida o schema e aplica os valores padrões
     *
     * @param array $dados_manifest
     *
     * @throws BlazarException
     */
    private static function validateSchema(array &$dados_manifest) {
        $object = ArrayRes::array2object($dados_manifest);

        // Força a criação do índice para ele receber os defaults
        if (!isset($object->configs)) $object->configs = new \stdClass();
        // Aplica o caminho real do diretório de logs
        if (!isset($object->configs->logs_dir)) $object->configs->logs_dir = Log::DEFAULT_DIR;
        if (!isset($object->configs->logs_dir)) $object->configs->texts_dir = Text::DEFAULT_DIR;

        // Força o tipo "objeto" caso o índice exista
        if (isset($object->data)) $object->data = (object)$object->data;
        if (isset($object->dbs)) $object->dbs = (object)$object->dbs;
        if (isset($object->map)) $object->map = (object)$object->map;

        $validator = new Validator();
        $schema = (object)['$ref' => 'file://' . realpath(self::SCHEMA_PATH)];

        // Aplica os valores padrões
        $validator->validate($object, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);
        // Quando possível converte o tipo para o formato correto exigido
        $validator->validate($object, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

        if (!$validator->isValid()) {
            $error = ["JSON does not validate. Violations:\n"];

            foreach ($validator->getErrors() as $error) {
                $error[] = sprintf("[%s] %s\n", $error['property'], $error['message']);
            }

            throw new BlazarException(implode("\n", $error));
        }

        $dados_manifest = ArrayRes::object2array($object);
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

        $dados_manifest = @file_get_contents($local);

        if ($dados_manifest === null) {
            throw new BlazarException("Manifest: O arquivo \"$file_name\" não foi encontrado.");
        }

        // Verifica inclusões de arquivos
        preg_match_all('~[\"|\']include>>(.+?)[\"|\']~', $dados_manifest, $retorno);

        // Aplica o que foi encontrado nos includes no json principal
        foreach ($retorno[0] as $index => $value) {
            if (file_exists(SOURCE_DIR . "/" . $retorno[1][$index])) {
                $conteudo_json = file_get_contents(SOURCE_DIR . "/" . $retorno[1][$index]);

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
            define("CURRENT_ENV", (int)Manifest::config("env"));
        }

        // Aplica o padrão do mustache
        View::mustacheDefault(Manifest::config("view_render_mustache"));

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