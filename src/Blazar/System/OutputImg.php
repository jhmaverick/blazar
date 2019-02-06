<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\System;

use Blazar\Helpers\Images;
use Blazar\Helpers\StrRes;
use Exception;

/**
 * Classe de gerenciamento de requisições de imagens
 */
class OutputImg {
    /**
     * @param string|null $local um local predefinido para o local da imagem
     */
    public function __construct($local = null) {
        try {
            // Pega o local do arquivo ou trata o que foi passado no parametro
            if ($local == null) $local = str_replace(BASE, "", URL);
            else if (StrRes::startsWith($local, ROOT . "/")) $local = str_replace(ROOT . "/", "", $local);
            else if (StrRes::startsWith($local, BASE)) $local = str_replace(BASE, "", $local);

            // Chama uma imagem quebrando quando arquivo não existir
            if (!file_exists($local)) {
                echo "Mídia não encontrada.";
                return;
            }

            $img_info = getimagesize($local);
            $img_type = $img_info[2];

            // Verifica o formato da imagem
            switch ($img_type) {
                case 1:
                    header('Content-Type: image/gif');

                    break;

                case 2:
                    header('Content-Type: image/jpg');

                    break;

                case 3:
                    header('Content-Type: image/png');

                    break;

                default:
                    echo "Mídia não encontrada.";
                    return;
            }

            // Redimensionar
            if (
                ((isset($_GET['w']) && is_numeric($_GET['w'])) || (isset($_GET['h']) && is_numeric($_GET['h']))) &&
                !isset($_GET['x']) && !isset($_GET['y'])
            ) {
                $w = (isset($_GET['w']) && is_numeric($_GET['w'])) ? $_GET['w'] : null;
                $h = (isset($_GET['h']) && is_numeric($_GET['h'])) ? $_GET['h'] : null;

                Images::redimensionar($local, $w, $h);
            } // Crop
            else if (isset($_GET['w']) && is_numeric($_GET['w']) &&
                isset($_GET['w']) && is_numeric($_GET['w']) &&
                isset($_GET['x']) && is_numeric($_GET['x']) &&
                isset($_GET['y']) && is_numeric($_GET['y'])
            ) {
                if (!isset($_GET['mw']) || !isset($_GET['mh'])) {
                    Images::crop($local, $_GET['x'], $_GET['y'], $_GET['w'], $_GET['h']);
                } else {
                    $result = Images::crop($local, $_GET['x'], $_GET['y'], $_GET['w'], $_GET['h'], Images::RETURN_BASE64);
                    Images::redimensionar($result, $_GET['mw'], $_GET['mh'], null);
                }
            } // Exibe sem alterações
            else {
                readfile($local);
            }
        } catch (Exception $e) {
            Log::e("Image", $e);
        }
    }
}