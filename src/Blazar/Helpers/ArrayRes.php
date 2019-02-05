<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Helpers;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

/**
 * Recursos para tratamento de arrays
 */
final class ArrayRes {

    /**
     * Realiza busca em array multidimensional
     *
     * @param $haystack
     * @param $needle
     * @param null $index
     * @return int|mixed
     */
    public static function search($haystack, $needle, $index = null) {
        if (is_null($haystack)) return -1;

        $arrayIterator = new RecursiveArrayIterator($haystack);
        $iterator = new RecursiveIteratorIterator($arrayIterator);

        while ($iterator->valid()) {
            if (
                ((isset($index) && ($iterator->key() == $index)) || (!isset($index))) &&
                ($iterator->current() == $needle)
            ) {
                return $arrayIterator->key();
            }

            $iterator->next();
        }

        return -1;
    }
}