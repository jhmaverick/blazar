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
            return (object) json_decode($json);
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
            return (array) json_decode($json, true);
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