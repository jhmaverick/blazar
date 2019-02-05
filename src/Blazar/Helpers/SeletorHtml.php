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
 * Gerencia dados do html vindos de uma URL
 */
class SeletorHtml {
    private $link;
    private $html;
    private $veri;

    /**
     * SeletorHtml constructor.
     *
     * @param string $url
     */
    public function __construct($url) {
        $url = (substr($url, 0, 7) == 'http://' || substr($url, 0, 8) == 'https://') ? $url : "http://" . $url;
        if (substr_count($url, 'https://') != 0) {
            $url = explode('https://', $url);
            $url = "http://" . $url[1];
        }

        $this->link = $url;
        $this->veri = @fopen($url, "r");
        if ($this->veri) $this->html = file_get_contents($url);
    }

    /**
     * Pega as metas da pagina
     *
     * @param string|null $attr
     *
     * @return string
     */
    public function meta($attr = null) {
        if (!$this->veri) {
            return null;
        }

        $html = $this->html;

        $count_tag = substr_count($html, '<meta');

        $resultado = null;

        for ($i = 0; $i < $count_tag; $i++) {
            $primeiro = stristr($html, '<meta'); // Remove tudo antes da primeira aparição da tag
            $fechar = stripos($primeiro, '>') + 1; // Verifica o fim da tag
            $tag = substr($primeiro, 0, $fechar); // Pega a tag

            // Recolhe atributos
            if (substr_count($tag, '="')) $aspas = '"';
            else $aspas = "'";

            $retorno[1] = null; // Previne erros caso a meta so tenha um atributo

            $count_attr = substr_count($tag, '=' . $aspas); // Verifica quantos atributos existe dentro da tag
            for ($a = 0; $a < $count_attr; $a++) {
                // Nome do atributo
                $pos_attr = stripos($tag, '=' . $aspas);
                $fim_attr = -1;
                for ($s = 0; $s < strlen($tag); $s++) {
                    if (substr($tag, $pos_attr, 1) != " ") {
                        $pos_attr = ($pos_attr - 1);
                        $fim_attr += 1;
                    }
                }
                $nattr = substr($tag, ($pos_attr + 1), $fim_attr);

                // Valor do atributo
                $pos_valor = stripos($tag, '=' . $aspas);

                $inicio = substr($tag, ($pos_valor + 2));
                $fechar = stripos($inicio, $aspas);
                $nvalor = substr($inicio, 0, $fechar);

                // Prepara valo e atributo
                if ($nattr != "content") $retorno[$a] = $nattr . '=' . $nvalor;
                else $retorno[$a] = $nvalor;

                // Retorna a string com as atributos restantes para a proxima busca
                $tag = substr($inicio, ($fechar + 1));
            }

            $resultado[$retorno[0]] = $retorno[1];

            // Retorna a string com as tags restantes para a proxima busca
            $html = stristr($primeiro, '>');
        }

        if ($attr != null) {
            if (array_key_exists($attr, $resultado)) $resultado = $resultado[$attr];
            else $resultado = "array inválido";
        }

        return $resultado;
    }

    /**
     * Pega o titulo da pagina
     *
     * @return mixed
     */
    public function title() {
        if (!$this->veri) {
            return null;
        }

        $html = $this->html;

        $title = stristr($html, '<title'); // Remove tudo antes do title
        $ini_valor = stripos($title, '>') + 1; // Remove possiveis atributos do title

        $ntitle = substr($title, $ini_valor); // Pega o conteudo

        $ntitle = explode("</title", $ntitle); // Remove tudo depois do title
        $ntitle = $ntitle[0]; // Seleciona primeiro array

        $resultado = $ntitle;

        return $resultado;
    }

    /**
     * Pega Icone
     *
     * @return string
     */
    public function ico() {
        if (!$this->veri) {
            return null;
        }

        $html = $this->html;
        $url = $this->link;

        if (substr_count($html, 'rel="shortcut icon"')) $aspas = '"';
        else $aspas = "'";

        $ico = stristr($html, 'rel=' . $aspas . 'shortcut icon' . $aspas); // Remove tudo antes do attr
        $ini_valor = stripos($ico, 'href=' . $aspas) + 6; // Pega posição do href

        $nico = substr($ico, $ini_valor); // Pega o valor

        $nico = explode($aspas, $nico); // Remove tudo depois das aspas finais
        $nico = $nico[0]; // Seleciona primeiro array

        $nico = (substr($nico, 0, 7) == 'http://' || substr($nico, 0, 8) == 'https://' || substr($nico, 0, 2) == '//') ? $nico : $url . "/" . $nico;

        $resultado = $nico;

        return $resultado;
    }
}