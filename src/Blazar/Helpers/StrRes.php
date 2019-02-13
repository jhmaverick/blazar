<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Helpers;

/**
 * Recursos para tratamento de strings
 */
class StrRes {
    /**
     * Verifica caracteres no inicio da string
     *
     * @param string $haystack A string que receberá a busca
     * @param string $needle O que será buscado na string
     *
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    /**
     * Verifica caracteres no fim da string
     *
     * @param string $haystack A string que receberá a busca
     * @param string $needle O que será buscado na string
     *
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle): bool {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }

    /**
     * Gera string randomica
     *
     * @param int $length Quantidade de caracteres
     *
     * @return string
     */
    public static function randstring(int $length): string {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
    }

    /**
     * Adiciona caracter em uma string
     *
     * @param string $str
     * @param string $caracter Caracter que sera adicionado
     * @param int $pos Posição
     *
     * @return string
     */
    public static function addCharacter(string $str, string $caracter, int $pos): string {
        $antes = substr($str, 0, $pos);
        $depois = substr($str, $pos);
        $str = $antes . $caracter . $depois;

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
     * @param string $texto
     * @param string $separador
     *
     * @return string
     */
    public static function prepareUrl(string $texto, string $separador = "-"): string {
        $texto = strtolower(html_entity_decode($texto));

        $charComAcento = ["á", "à", "â", "ã", "ä", "é", "è", "ê", "ë", "í", "ì", "î", "ï", "ó", "ò", "ô", "õ", "ö", "ú", "ù", "û", "ü", "ç", "Á", "À", "Â", "Ã", "Ä", "É", "È", "Ê", "Ë", "Í", "Ì", "Î", "Ï", "Ó", "Ò", "Ô", "Õ", "Ö", "Ú", "Ù", "Û", "Ü", "Ç"];
        $charSemAcento = ["a", "a", "a", "a", "a", "e", "e", "e", "e", "i", "i", "i", "i", "o", "o", "o", "o", "o", "u", "u", "u", "u", "c", "A", "A", "A", "A", "A", "E", "E", "E", "E", "I", "I", "I", "I", "O", "O", "O", "O", "O", "U", "U", "U", "U", "C"];
        $texto = str_replace($charComAcento, $charSemAcento, $texto);

        $texto = str_replace(" ", $separador, $texto);
        $texto = preg_replace("/[^a-z0-9-]/", "", $texto);

        return $texto;
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
     * @param bool $alternativo Mustache utilizando "<%%>" invés de "{{}}"
     *
     * @return array
     */
    public static function extractMustacheVars(?string $str, bool $alternativo = false) {
        if ($alternativo) preg_match_all('/\<\%([^\s\}]+?)\%\>/i', $str, $matches);
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

    /**
     * @deprecated Use StrRes::replaceFirst
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function str_freplace(string $search, string $replace, string $subject): string {
        return self::replaceFirst($subject, $search, $replace);
    }

    /**
     * Aplica o str_replace apenas na primeira ocorrencia
     *
     * @param string $subject O texto original onde as as buscar irão ocorrer
     * @param string $search O texto que deve ser substituido
     * @param string $replace Qual texto será inserido no lugar
     *
     * @return mixed
     */
    public static function replaceFirst(string $subject, string $search, string $replace): string {
        // Find the position of the first occurrence
        $pos = !empty($search) ? strpos($subject, $search) : 0;

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    /**
     * @deprecated Use StrRes::replaceLast
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     */
    public static function str_lreplace(string $search, string $replace, string $subject): string {
        return self::replaceLast($subject, $search, $replace);
    }

    /**
     * Aplica o str_replace apenas na ultima ocorrencia
     *
     * @param string $subject O texto original onde as as buscar irão ocorrer
     * @param string $search O texto que deve ser substituido
     * @param string $replace Qual texto será inserido no lugar
     *
     * @return mixed
     */
    public static function replaceLast(string $subject, string $search, string $replace): string {
        // Find the position of the last occurrence
        $pos = !empty($search) ? strpos($subject, $search) : 0;

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }
}