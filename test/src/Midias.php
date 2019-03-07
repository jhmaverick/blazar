<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace TestApp;

use Blazar\Component\Image\Image;
use Blazar\Component\Image\ImageException;
use Blazar\Core\Manifest;
use BrightNucleus\MimeTypes\MimeTypes;

class Midias {
    public function __construct() {
        $local_file = str_replace(URL_BASE, '', URL);
        $local_file = explode('?', $local_file)[0];

        // Verifica se o arquivo existe
        if (!file_exists($local_file)) {
            echo 'Mídia não encontrada.';

            return;
        }

        $mimes = new MimeTypes();
        $ext = pathinfo($local_file, PATHINFO_EXTENSION);
        $mime = $mimes->getTypesForExtension($ext)[0];

        try {
            if (isset($_GET['download'])) {
                header('Content-type: ' . $mime);
                header('Content-disposition: attachment; filename="' . $local_file . '"');
                header('Content-Length: ' . filesize($local_file));
                readfile($local_file);
            } elseif ((isset($_GET['w']) || isset($_GET['h'])) && ($ext == 'jpg' || $ext == 'png')) {
                $w = $_GET['w'] ?? null;
                $h = $_GET['h'] ?? null;
                $x = $_GET['x'] ?? null;
                $y = $_GET['y'] ?? null;
                $mw = $_GET['mw'] ?? null;
                $mh = $_GET['mh'] ?? null;

                Image::output($local_file, $w, $h, $x, $y, $mw, $mh);
            } else {
                Image::output($local_file, null, Manifest::config('max_img_height'));
            }
        } catch (ImageException $e) {
        }
    }
}