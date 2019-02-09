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
use Blazar\Helpers\StrRes;
use Exception;
use Mustache_Engine;

/**
 * Class de leitura de textos do sistema
 *
 * O diretório de textos padrão está definido em (ROOT . "texts/"), utilize o metodo "setDefaultDir" para alterar.
 */
class Text {

    // Diretório padrão dos textos
    private static $default_dir = ROOT . "/texts/";
    private static $blazar_dir = BLAZAR_ROOT . "/texts/";
    private static $lang = null;

    // Arquivo padrão dos textos
    const FILE_MAIN = "main";

    // Lista de arquivos já carregados
    private static $loaded_files = [];
    private static $ow_loaded_files = [];

    /**
     * Definir diretório padrão de textos
     * @param string $default_dir Caminho para o diretório
     */
    public static function setDefaultDir(string $default_dir) {
        if (!StrRes::endsWith($default_dir, "/")) $default_dir = $default_dir . "/";
        self::$default_dir = $default_dir;
    }

    /**
     * Define um idioma para ser mesclado com o arquivo principal
     *
     * To arquivo de texto chamado irá procurar por uma versão com a extensão do idioma setado<br>
     * Exemplo: main.json e main.pt.json
     *
     * @method static setLang() description
     * @param string $lang Abreviação do idioma
     */
    public static function setLang(string $lang) {
        $lang = trim($lang, ". ");
        $lang = StrRes::str_lreplace(".json", "", $lang);

        self::$lang = $lang;
    }

    /**
     * Pegar texto em arquivos JSON
     *
     * Os textos do framework devem iniciar com "bzr-" ex: "bzr-title" para chamar do arquivo main ou "bzr-example/title"
     * para chamar o indice title no arquivo example.
     *
     * @param string $get_text <p>
     * O indice do texto desejado.<br>
     * Ex: <code>"msg_boas_vindas"</code>
     * O arquivo padrão chamado será o "main", para pegar o texto dentro de outro arquivo informe o nome do arquivo e
     * a chave do json separados por barra.<br>
     * Ex: <code>"nome_arquivo/msg_boas_vindas"</code><br>
     * Não é necessario informar ".json" no nome do arquivo.
     * </p>
     * @param array $mustache_hash Parametros para substituir com mustache
     *
     * @return string|null retorna null se o texto não existir
     */
    public static function get(string $get_text, array $mustache_hash = []): ?string {
        // Se esta utilizando os textos do framework
        $blazar_txt = false;

        // Verifica se o pedido é do framework
        if (StrRes::startsWith($get_text, "bzr-")) {
            $blazar_txt = true;
            $get_text = StrRes::str_freplace("bzr-", "", $get_text);
        }

        // Verifica se o arquivo foi passado junto a chave
        if (substr_count($get_text, "/") > 0) {
            $file_name = implode("/", explode("/", $get_text, -1));
            $novo = explode("/", $get_text);
            $key = end($novo);
        } else {
            // Utilizar main file
            $file_name = self::FILE_MAIN;
            $key = $get_text;
        }

        // Verifica se esta tentando utilizar algum arquivo de texto com nome "bzr"
        if ($file_name === "bzr") {
            Log::e("\"bzr\" é reservado para o framework, arquivos de texto não podem ser criados com esse nome.",
                "Texto chamado: \"" . $get_text . "\"",
                true);
            return "";
        }

        // Verifica se o texto era do framework
        if ($blazar_txt) $file_name = "bzr-" . $file_name;

        try {
            // Pega os dados do arquivo
            $list = self::prepare($file_name);

            // Verifica o indice existe no arquivo
            if ($list != null && isset($list[$key])) $str = $list[$key];
            else return null;

            if (count($mustache_hash) > 0) {
                // Substitui parametros do mustache
                $mustache = new Mustache_Engine();
                $str = $mustache->render($str, $mustache_hash);
            }

            return $str;
        } catch (Exception $e) {
            // Salva log apenas para json incorreto
            if ($e->getCode() == 2) Log::e($e);
            return null;
        }
    }

    /**
     * Pegar todos os textos de um arquivo
     *
     * Os textos do framework devem iniciar com "bzr-" ex: "bzr-example" ou apenas "bzr" para chamar o main.
     *
     * @param string $file_name <p>
     * O nome do arquivo onde esta localizado o texto desejado(Não é necessario informar ".json")<br>
     * Se o arquivo não for informado o arquivo padrão será o "main"
     * </p>
     * @param array $matriz_mustache_hash <p>
     * Parametros para substituir com mustache.<br>
     * Este parametro é uma Matriz com 2 niveis. O primeiro nivel deve informar para qual indice os dados irão.<br>
     * <code>
     * [
     *      "dados_user" => ["nome"=> "João", "idade" => 25],
     *      "mensagem" => ["grupo"=> "Grupo Exemplo"]
     * ]
     * </code>
     * Os indices que não tiverem os valores informados irão retornar com as hashes do mustache Ex: "Olá {{nome}}".
     * </p>
     *
     * @return array|null Retorna null caso o arquivo não exista
     */
    public static function getAll(?string $file_name = self::FILE_MAIN, array $matriz_mustache_hash = []): ?array {
        // Define o main do sistema caso bzr seja informado
        if ($file_name === "bzr" || $file_name === "bzr-") $file_name = "bzr-" . self::FILE_MAIN;

        try {
            // Pega os dados do arquivo
            $list = self::prepare($file_name);
            if ($list == null) return null;

            if (count($matriz_mustache_hash) > 0) {
                $mustache = new Mustache_Engine();

                foreach ($list as $i => $v) {
                    // Verifica se existe dados para aplicar no indice
                    if (isset($matriz_mustache_hash[$i]) && is_array($matriz_mustache_hash[$i])) {
                        // Substitui parametros do mustache
                        $list[$i] = $mustache->render($v, $matriz_mustache_hash[$i]);
                    }
                }
            }

            return $list;
        } catch (Exception $e) {
            // Salva log apenas para json incorreto
            if ($e->getCode() == 2) Log::e($e);
            return null;
        }
    }

    /**
     * Pega o texto com Sobre Escrita
     *
     * Procura primeiro no projeto e se não encontrar tenta no framework
     *
     * @param string $get_text
     * @param array $mustache_hash
     * @return string|null
     */
    public static function getOW(string $get_text, array $mustache_hash = []): ?string {
        // Remove prefixo do framework
        $get_text = StrRes::str_freplace("bzr-", "", $get_text);

        // Tenta pegar um texto do projeto
        $text = Text::get($get_text, $mustache_hash);
        // Se não encontrar tenta no framework
        if ($text === null) $text = Text::get("bzr-" . $get_text, $mustache_hash);

        return $text;
    }

    /**
     * Pega todos os textos de um arquivo com Sobre Escrita
     *
     * Procura primeiro no projeto e se não encontrar tenta no framework
     *
     * @param string|null $file_name
     * @param array $matriz_mustache_hash
     * @return array|null
     */
    public static function getAllOW(?string $file_name = self::FILE_MAIN, array $matriz_mustache_hash = []): ?array {
        // Remove prefixo do framework
        if (StrRes::startsWith($file_name, "bzr-")) $file_name = StrRes::str_freplace("bzr-", "", $file_name);
        else if (StrRes::startsWith($file_name, "bzr")) $file_name = StrRes::str_freplace("bzr", "", $file_name);

        $file_key = str_replace("/", "-", self::prepareFileName($file_name));

        if (!isset(self::$ow_loaded_files[$file_key])) {
            // Tenta pegar um texto do projeto
            $texts = Text::getAll($file_name, $matriz_mustache_hash);
            // Se não encontrar tenta no framework
            $texts_fm = Text::getAll("bzr-" . $file_name, $matriz_mustache_hash);

            if ($texts !== null && $texts_fm !== null) $final_text = array_merge($texts_fm, $texts);
            else if ($texts !== null) $final_text = $texts;
            else $final_text = $texts_fm;

            return self::$ow_loaded_files[$file_key] = $final_text;
        } else {
            return self::$ow_loaded_files[$file_key];
        }
    }

    /**
     * Verifica se o arquivo de texto já foi carregado e se necessario carrega
     *
     * @param string|null $file_name <p>
     * O nome do arquivo onde esta localizado o texto desejado(Não é necessario informar ".json")<br>
     * Se o arquivo não for informado o arquivo padrão será o "main.json"
     * </p>
     *
     * @param bool $looking_lang Se a busca é pelo idioma setado na variavel lang
     * @return array
     * @throws Exception
     */
    private static function prepare(?string $file_name = self::FILE_MAIN, bool $looking_lang = false): ?array {
        $file_name = self::prepareFileName($file_name);

        // Gera a chave pra salvar o nome do arquivo na lista de carregados
        $file_key = str_replace("/", "-", $file_name);

        // Aplica o caminho completo para o texto
        if (StrRes::startsWith($file_name, "bzr-")) {
            // Ajusta caminho para os textos do framework
            $name = StrRes::str_freplace("bzr-", "", $file_name);
            $name = self::$blazar_dir . $name;
        } else {
            // Caminho para textos do projeto
            $name = self::$default_dir . $file_name;
        }

        // Verifica se o arquivo já foi carregado
        if (!isset(self::$loaded_files[$file_key])) {
            if (file_exists($name . ".json")) $name = $name . ".json";
            else return null;

            // Remove comentarios e transforma em array
            $file_content = StrRes::removeComments(Files::read($name));
            if (json_decode($file_content, true)) {
                $list = json_decode($file_content, true);
            } else {
                throw new Exception("O conteúdo do arquivo \"" . $name . ".json\" não é um JSON.", 2);
            }

            // Verifica se existe um texto em outro idioma para mesclar
            if (self::$lang !== null && !$looking_lang) {
                $lang = trim(self::$lang, ". ");
                $other_lang = self::prepare($file_name . "." . $lang, true);

                if ($other_lang !== null) $list = array_merge($list, $other_lang);
            }

            // Se estiver buscando o idioma não salva nos arquivos carregados
            if ($looking_lang) return $list;
            else return self::$loaded_files[$file_key] = $list;
        } else {
            return self::$loaded_files[$file_key];
        }
    }

    /**
     * Remove .json do nome e limpa a string
     *
     * @param string|null $file_name
     * @return string
     */
    private static function prepareFileName(?string $file_name = self::FILE_MAIN): string {
        if ($file_name == null) $file_name = self::FILE_MAIN;
        $file_name = trim($file_name, " \t\n\r \v/");
        $file_name = StrRes::str_lreplace(".json", "", $file_name);

        return $file_name;
    }
}