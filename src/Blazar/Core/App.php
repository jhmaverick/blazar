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

/**
 * Classe de gerenciamento de dados da aplicação
 *
 * Responsável por gerenciar o status da aplicação como informações passadas por URL, navegação pelo mapa de classes...
 */
class App {

    /** Todos os parâmetros informados na URL */
    const PARAMS_ALL = 0;
    /** Parâmetro destinado a aplicação */
    const PARAMS_APP = 1;
    /** Parâmetro destinado a seleção de classes no mapa do manifest */
    const PARAMS_MAP = 2;

    private static $current_index = 0;
    protected static $max_index_map = 0;
    protected static $parameters = [];
    protected static $url_params = [];
    protected static $map_params = [];

    /**
     * Pega as informações do próximo parâmetro no mapa de classes com base na URL informada.
     *
     * @param string|null $index Força o retorno de apenas um indice do array vindo do método ClassMap::get.
     *
     * @return array|mixed|null <p>
     * Retorna os dados vindos do método ClassMap::get ou null caso um indice tenha sido informado e não exista no
     *     array.<br> Para mais informações sobre o retorno veja a documentação de {@see ClassMap::get}
     * </p>
     */
    public static function next(string $index = null) {
        $param = App::get(App::$current_index);
        if ($param !== null) App::$current_index++;

        if ($index !== null) return $param[$index] ?? null;

        return $param;
    }

    /**
     * Pega as informações do ultimo parâmetro chamado pelo método ClassMap::next.
     *
     * @param string|null $index Força o retorno de apenas um indice do array vindo do método ClassMap::get.
     *
     * @return array|mixed|null <p>
     * Retorna os dados vindos do metodo ClassMap::get ou null caso um indice tenha sido informado e não exista no
     *     array.<br> Para mais informações sobre o retorno veja a documentação de {@see ClassMap::get}
     * </p>
     */
    public static function current(string $index = null) {
        $param = App::get(App::$current_index - 1);

        if ($index !== null) return $param[$index] ?? null;

        return $param;
    }

    /**
     * Pegar os dados do parâmetro da URL processada.
     *
     * Os dados são gerados através do "map" do manifest e o que foi requisitado nos parâmetros da URL.<br>
     * Cada índice representa um parâmetro chamado.
     * <br>
     * Indices do retorno:<br>
     * [class] => string(Classe para inicializar)<br>
     * [name] => string(O nome do indice)<br>
     * [index] => int(Seu nível no map)<br>
     * [route] => string(A rota no map)
     * [url_path] => string(Seu path junto a url base)
     *
     * @param int|string|null $index <p>
     * Se for <b>int</b>, retorna a posição parâmetro na url(A contagem começa a partir do 0).<br>
     * Se for <b>string</b>, retorna o parâmetro com o nome informado.
     * Se existir mais de um parâmetro com mesmo nome na arvore, será retornado o primeiro encontrado, para evitar
     * isto informe a árvore na string separando por barra Ex: "usuarios/usuarios".<br>
     * Se <b>null</b>, retorna um array com os dados de todos o mapa de classes.<br>
     * </p>
     *
     * @return array|null <p>
     * O retorno depende do tipo do index informado.<br>
     * Se o index informado não for encontrado retorna null.
     * </p>
     */
    public static function get($index = null) {
        if ($index !== null) {
            if (is_int($index)) {
                // Retorna os dados do index requisitado ou null se não existir
                return App::$map_params[$index] ?? null;
            } else {
                $arvore = explode("/", $index);
                $index = (count($arvore) > 1) ? $index = end($arvore) : $index;

                foreach (App::$map_params as $i => $v) {
                    // Verifica se é o index desejado e se ele é a ultima ocorrencia dele caso tenha sido definido com "/"
                    if ($v['name'] == $index && array_count_values($arvore)[$v['name']] == 1) {
                        return $v;
                    }

                    // Remove do array
                    if (($rm = array_search($v['name'], $arvore)) !== false) {
                        unset($arvore[$rm]);
                    }
                }

                // Se não existir um parâmetro com esse nome retorna null
                return null;
            }
        } else {
            // Retorna todos os parâmetros
            return App::$map_params;
        }
    }

    /**
     * Parâmetros de execução do sistema com base nos dados da URL amigavel e do Manifest map.
     *
     * Os parâmetros omitidos na URL são retornados utilizando os mapas definidos como "main" no manifest.
     *
     * @param int|null $index <p>
     * Retorna o nome do parâmetro na posição informada.<br>
     * Se não for informado retorna todas.
     * </p>
     * @param int $type <p>
     * Quais são os parâmetros que serão retornados.<br>
     * {@see Application::PARAMS_ALL}, {@see Application::PARAMS_APP} e {@see Application::PARAMS_MAP}
     * </p>
     *
     * @return array|string|null <p>
     * O retorno depende do tipo do index informado.<br>
     * Se o index informado não for encontrado retorna null.
     * </p>
     */
    public static function param(int $index = null, int $type = App::PARAMS_ALL) {
        if ($type === App::PARAMS_ALL) {
            if ($index !== null) {
                if (isset(App::$parameters[$index])) {
                    return App::$parameters[$index];
                } else {
                    return null;
                }
            }

            return App::$parameters;
        } else if ($type === App::PARAMS_APP) {
            $url = self::param();

            $inicio = App::maxIndex() + 1;

            $params = [];
            for ($i = $inicio; $i < count($url); $i++) {
                $params[] = $url[$i];
            }

            if ($index !== null) {
                if (isset($params[$index])) {
                    return $params[$index];
                } else {
                    return null;
                }
            }

            return $params;
        } else if ($type === App::PARAMS_MAP) {
            $url = self::param();

            $fim = App::maxIndex();

            $params = [];
            for ($i = 0; $i <= $fim; $i++) {
                $params[] = $url[$i];
            }

            if ($index !== null) {
                if (isset($params[$index])) {
                    return $params[$index];
                } else {
                    return null;
                }
            }

            return $params;
        } else {
            return null;
        }
    }

    /**
     * Retorna apenas os parâmetros informados por URL amigas.
     *
     * @param int|null $index
     *
     * @return array|string|null
     */
    public static function urlParam($index = null) {
        if ($index !== null) {
            if (isset(App::$url_params[$index])) {
                return App::$url_params[$index];
            } else {
                return null;
            }
        }

        return App::$url_params;
    }

    /**
     * Informa qual foi o ultimo indice chamado pelo método next.
     *
     * @return int
     */
    public static function currentIndex(): int {
        return App::$current_index;
    }

    /**
     * Retorna o indice da ultima casa dos parâmetros de mapa.
     *
     * @return int
     */
    public static function maxIndex(): int {
        return App::$max_index_map;
    }

}