<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\System;

/**
 * Classe de navegação pelo manifest "maps" informados nos parâmetros da URL
 */
class ClassMap extends Application {

    private static $current_index = 0;

    /**
     * Pega as informações do próximo parâmetro no mapa de classes com base na URL informada.
     *
     * @param string|null $index Força o retorno de apenas um indice do array vindo do método ClassMap::get.
     *
     * @return array|mixed|null <p>
     * Retorna os dados vindos do metodo ClassMap::get ou null caso um indice tenha sido informado e não exista no
     *     array.<br> Para mais informações sobre o retorno veja a documentação de {@see ClassMap::get}
     * </p>
     */
    public static function next(string $index = null) {
        $param = ClassMap::get(ClassMap::$current_index);
        if ($param !== null) ClassMap::$current_index++;

        if ($index !== null) return $param[$index] ?? null;

        return $param;
    }

    /**
     * Pegar os dados do parâmetro da URL processada.
     *
     * Os dados são gerados atraves do "map" do manifest e o que foi requisitado nos parâmetros da URL.<br>
     * Cada indice representa um parâmetro chamado.
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
                return Application::$map_params[$index] ?? null;
            } else {
                $arvore = explode("/", $index);
                $index = (count($arvore) > 1) ? $index = end($arvore) : $index;

                foreach (Application::$map_params as $i => $v) {
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
            return Application::$map_params;
        }
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
        $param = ClassMap::get(ClassMap::$current_index - 1);

        if ($index !== null) return $param[$index] ?? null;

        return $param;
    }

    /**
     * Informa qual foi o ultimo indice chamado pelo método next.
     *
     * @return int
     */
    public static function currentIndex(): int {
        return ClassMap::$current_index;
    }

    /**
     * Retorna o indice da ultima casa dos parâmetros de mapa.
     *
     * @return int
     */
    public static function maxIndex(): int {
        return Application::$max_index_map;
    }
}