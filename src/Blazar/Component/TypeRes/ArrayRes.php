<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\TypeRes;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use stdClass;

/**
 * Recursos para tratamento de arrays.
 */
class ArrayRes {

    /**
     * Transforma um array associativo em um objeto de forma recursiva.
     *
     * @param array $array
     *
     * @return stdClass|null retorna null em caso de falha
     */
    public static function array2object(array $array): ?stdClass {
        $json = json_encode($array);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        } else {
            return (object)json_decode($json);
        }
    }

    /**
     * Transforma um objeto em um array associativo de forma recursiva.
     *
     * @param stdClass $object
     *
     * @return array|null retorna null em caso de falha
     */
    public static function object2array(stdClass $object): ?array {
        $json = json_encode($object);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        } else {
            return (array)json_decode($json, true);
        }
    }

    /**
     * Pega o dado de um array multidimensional seguindo uma rota informada.
     *
     * @param array $array Array que receberá a busca.
     * @param string $route Ex: "nivel1/nivel2/indice".
     *
     * @return array|mixed|null Retorna um caso a rota não exista.
     */
    public static function route(array $array, string $route) {
        $route = preg_replace('/\/+/', '/', $route);
        $route = trim($route, '/ ');

        if ($route == '') {
            return $array;
        }

        // Percorre a rota informada
        $arvore = explode('/', $route);

        for ($i = 0; $i < count($arvore); $i++) {
            $atual = $arvore[$i];

            if (!isset($array[$atual])) {
                return null;
            }

            $array = $array[$atual];
        }

        return $array;
    }

    /**
     * Insere dado em um array multidimensional seguindo uma rota informada.
     *
     * @param array $array Array que receberá o valor.
     * @param string $route Ex: "nivel1/nivel2/indice".
     * @param mixed $value Valor a ser inserido na rota.
     * @param bool $merge Aplica um merge caso a rota e o valor informado sejam do tipo array
     *
     * @return array|mixed|null <p>
     * Retorna um array com o valor inserido na rota.<br>
     * Se a rota não for encontrada o array original é retornado.
     * </p>
     */
    public static function insertInRoute(array $array, string $route, $value, bool $merge = false) {
        $route = preg_replace('/\/+/', '/', $route);
        $route = trim($route, '/ ');

        if ($route == '') {
            if ($merge && is_array($value)) {
                $array = array_merge($array, $value);
            } else {
                $array = $value;
            }

            return $array;
        }

        // Percorre a rota informada
        $arvore = explode('/', $route);

        $last = &$array;
        for ($i = 0; $i < count($arvore); $i++) {
            $temp = &$last;
            $atual = $arvore[$i];

            // Cria o indice se ele não existir
            if (!isset($temp[$atual])) {
                $temp[$atual] = [];
            }

            // Insere o valor caso seja o indice final da rota
            if (isset($temp[$atual]) && ($i + 1) == count($arvore)) {
                if ($merge && is_array($temp[$atual]) && is_array($value)) {
                    $temp[$atual] = array_merge($temp[$atual], $value);
                } else {
                    $temp[$atual] = $value;
                }

                return $array;
            }

            unset($last);
            $last = &$temp[$atual];
        }

        return $array;
    }

    /**
     * Verifica se um valor existe em um array multidimensional.
     *
     * @param array $array Array que receberá a busca.
     * @param mixed $needle Valor a ser buscado.
     * @param string|null $index O valor só será valido se estiver em um indice com este nome.
     *
     * @return bool
     */
    public static function search(array $array, $needle, ?string $index = null): bool {
        if (is_null($array)) {
            return false;
        }

        $arrayIterator = new RecursiveArrayIterator($array);
        $iterator = new RecursiveIteratorIterator($arrayIterator);

        while ($iterator->valid()) {
            if (
                ((isset($index) && ($iterator->key() == $index)) || (!isset($index))) &&
                ($iterator->current() == $needle)
            ) {
                //return $arrayIterator->key();
                return true;
            }

            $iterator->next();
        }

        return false;
    }
}