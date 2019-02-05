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
 * Recursos para datas
 */
final class DateTime {

    /**
     * Pega o dia da semana de uma data por extenso
     *
     * @param string $data Data no padrão mysql (Ex: 2018-01-01)
     *
     * @return string
     */
    public static function diasemana(string $data) {
        $ano = substr($data, 0, 4);
        $mes = substr($data, 5, -3);
        $dia = substr($data, 8, 9);

        $diasemana = date("w", mktime(0, 0, 0, $mes, $dia, $ano));

        switch ($diasemana) {
            case 0:
                return "Domingo";
                break;

            case 1:
                return "Segunda-Feira";
                break;

            case 2:
                return "Terça-Feira";
                break;

            case 3:
                return "Quarta-Feira";
                break;

            case 4:
                return "Quinta-Feira";
                break;

            case 5:
                return "Sexta-Feira";
                break;

            case 6:
                return "Sábado";
                break;

            default:
                return null;
        }
    }

    /**
     * Transfoma mes numerico em string
     *
     * @param int $mes
     *
     * @return String
     */
    public static function converteMes($mes) {
        $mes = $mes + 0;

        switch ($mes) {
            case 1:
                return "Janeiro";
                break;

            case 2:
                return "Fevereiro";
                break;

            case 3:
                return "Março";
                break;

            case 4:
                return "Abril";
                break;

            case 5:
                return "Maio";
                break;

            case 6:
                return "Junho";
                break;

            case 7:
                return "Julho";
                break;

            case 8:
                return "Agosto";
                break;

            case 9:
                return "Setembro";
                break;

            case 10:
                return "Outubro";
                break;

            case 11:
                return "Novembro";
                break;

            case 12:
                return "Dezembro";
                break;

            default:
                return null;
        }
    }
}
