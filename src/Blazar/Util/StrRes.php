<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Util;

/**
 * Recursos para tratamento de strings
 */
class StrRes {

    /**
     * Verifica caracteres no inicio da string
     *
     * @param string $str A string que receberá a busca
     * @param string $search O que será buscado na string
     *
     * @return bool
     */
    public static function startsWith(string $str, string $search): bool {
        // search backwards starting from haystack length characters from the end
        return $search === "" || strrpos($str, $search, -strlen($str)) !== false;
    }

    /**
     * Verifica caracteres no fim da string
     *
     * @param string $str A string que receberá a busca
     * @param string $search O que será buscado na string
     *
     * @return bool
     */
    public static function endsWith(string $str, string $search): bool {
        // search forward starting from end minus needle length characters
        return $search === "" || (($temp = strlen($str) - strlen($search)) >= 0 && strpos($str, $search, $temp) !== false);
    }

    /**
     * Aplica o str_replace apenas na primeira ocorrencia
     *
     * @param string $str O texto original onde a busca irá ocorrer
     * @param string $search O texto que deve ser substituido
     * @param string $replace Qual texto será inserido no lugar
     *
     * @return mixed
     */
    public static function replaceFirst(string $str, string $search, string $replace): string {
        // Find the position of the first occurrence
        $pos = !empty($search) ? strpos($str, $search) : 0;

        if ($pos !== false) {
            $str = substr_replace($str, $replace, $pos, strlen($search));
        }

        return $str;
    }

    /**
     * Aplica o str_replace apenas na ultima ocorrencia
     *
     * @param string $str O texto original onde a busca irá ocorrer
     * @param string $search O texto que deve ser substituido
     * @param string $replace Qual texto será inserido no lugar
     *
     * @return mixed
     */
    public static function replaceLast(string $str, string $search, string $replace): string {
        // Find the position of the last occurrence
        $pos = !empty($search) ? strpos($str, $search) : 0;

        if ($pos !== false) {
            $str = substr_replace($str, $replace, $pos, strlen($search));
        }

        return $str;
    }

    /**
     * Gera string randomica
     *
     * @param int $length Quantidade de caracteres
     *
     * @return string
     */
    public static function randstr(int $length): string {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
    }

    /**
     * Adiciona caracter em uma string
     *
     * @param string $str
     * @param string $character Caracter que sera adicionado
     * @param int $pos Posição
     *
     * @return string
     */
    public static function addCharacter(string $str, string $character, int $pos): string {
        $antes = substr($str, 0, $pos);
        $depois = substr($str, $pos);
        $str = $antes . $character . $depois;

        return $str;
    }

    /**
     * Função para limitar o texto
     *
     * @param string $str Texto para limitar
     * @param int $num Quantidade de caracteres
     *
     * @return string
     */
    public static function limit(string $str, int $num): string {
        $count = null;
        $total = 0;
        $arr = [];
        $tamString = strlen($str);

        if ($tamString != $num) {
            $str = explode(' ', $str);

            for ($i = 0; $i < count($str); $i++) {
                $count = strlen($str[$i]);
                if (($total + $count + 3) < $num) {
                    $total = $total + $count + 1;
                    array_push($arr, $str[$i]);
                } else {
                    break;
                }
            }

            $textoF = implode(' ', $arr);
            if (strlen($textoF) < $tamString)
                return $textoF . "...";
            else return $textoF;
        } else return $str;
    }

    /**
     * Prepara um texto para ser usado na url
     *
     * @param string $str
     * @param string $separator
     *
     * @return string
     */
    public static function string2url(string $str, string $separator = "-"): string {
        $str = strtolower(html_entity_decode($str));

        $charComAcento = ["á", "à", "â", "ã", "ä", "é", "è", "ê", "ë", "í", "ì", "î", "ï", "ó", "ò", "ô", "õ", "ö", "ú", "ù", "û", "ü", "ç", "Á", "À", "Â", "Ã", "Ä", "É", "È", "Ê", "Ë", "Í", "Ì", "Î", "Ï", "Ó", "Ò", "Ô", "Õ", "Ö", "Ú", "Ù", "Û", "Ü", "Ç"];
        $charSemAcento = ["a", "a", "a", "a", "a", "e", "e", "e", "e", "i", "i", "i", "i", "o", "o", "o", "o", "o", "u", "u", "u", "u", "c", "A", "A", "A", "A", "A", "E", "E", "E", "E", "I", "I", "I", "I", "O", "O", "O", "O", "O", "U", "U", "U", "U", "C"];
        $str = str_replace($charComAcento, $charSemAcento, $str);

        $str = str_replace(" ", $separator, $str);
        $str = preg_replace("/[^a-z0-9-]/", "", $str);

        return $str;
    }

    /**
     * Remove comentarios "/* *\/" e "/" de uma string.
     *
     * @param string $str A string para remover os comentarios.
     *
     * @return  string
     */
    public static function removeComments(string $str): string {
        // search and remove comments like /* */ and //
        $str = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $str);
        return $str;
    }

    /**
     * Extrai as variaveis mustache de uma string
     *
     * @param string $str Texto para extrair as variaveis
     * @param bool $alternative Mustache utilizando "<%%>" invés de "{{}}"
     *
     * @return array
     */
    public static function extractMustacheVars(?string $str, bool $alternative = false) {
        if ($alternative) preg_match_all('/\<\%([^\s\}]+?)\%\>/i', $str, $matches);
        else preg_match_all('/\{\{([^\s\}]+?)\}\}/i', $str, $matches);

        return $matches;
    }

    /**
     * Verifica se é um md5
     *
     * @param string $md5
     *
     * @return bool
     */
    public static function isValidMd5(string $md5): bool {
        return preg_match('/^[a-f0-9]{32}$/', $md5) === 1 ? true : false;
    }

}