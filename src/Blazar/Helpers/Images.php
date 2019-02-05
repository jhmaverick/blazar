<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Helpers;

use Blazar\Manifest;

/**
 * Recursos para manipulação de imagens
 */
abstract class Images {
    const RETURN_BASE64 = "return_base64";
    const SHOW_WINDOW = "show_window";

    /**
     * Upload de imagens
     *
     * @param array $arquivo
     * @param string|null $dir_saida - O diretório de saida(se null exibe diretamente no navegador)
     * @param int $largura
     * @param int $altura
     * @param bool $m_menores - Manter tamanho se forem menores que o exigido
     *
     * @return string
     * @throws ImagesException
     */
    public static function upload($arquivo, $dir_saida, $largura = null, $altura = null, $m_menores = true) {
        $temp = $arquivo['tmp_name'];
        $fileParts = pathinfo($arquivo['name']);

        $formato = strtolower($fileParts['extension']);

        if ($formato == 'jpg' || $formato == 'jpeg') {
            $img = imagecreatefromjpeg($temp);
        } elseif ($formato == 'png') {
            $img = imagecreatefrompng($temp);
        } elseif ($formato == 'gif') {
            $img = imagecreatefromgif($temp);
        } else {
            throw new ImagesException("Formato inválido.");
        }

        // Caso manter menor seja verdadeiro e tamanho tambem seja menor ou
        // se largura e altura seja menor aplica tamanhos padrões da imagem
        if (($m_menores && imagesx($img) < $largura && imagesy($img) < $altura) ||
            ($largura == null && $altura == null)
        ) {
            $largura = imagesx($img);
            $altura = imagesy($img);
        }

        // Evita que imagem seja maior que o permitido pelo sistema
        if ($largura != null && $largura > Manifest::getConfig("max_img_width"))
            $largura = Manifest::getConfig("max_img_width");
        if ($altura != null && $altura > Manifest::getConfig("max_img_height"))
            $altura = Manifest::getConfig("max_img_height");

        // Calcula dimensão que estiver faltando
        if ($largura != null && (imagesx($img) >= imagesy($img) || $altura == null)) {
            $altura = (imagesy($img) * $largura) / imagesx($img);
        } else if ($altura != null && (imagesy($img) > imagesx($img) || $largura == null)) {
            $largura = (imagesx($img) * $altura) / imagesy($img);
        }

        if ($formato == 'png') {
            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($img);

            // Transparencia do png
            if ($isTrueColor) {
                $nova = imagecreatetruecolor($largura, $altura);
                imagealphablending($nova, false);
                imagesavealpha($nova, true);
            } else {
                $nova = imagecreate($largura, $altura);
                imagealphablending($nova, false);
                $transparent = imagecolorallocatealpha($nova, 0, 0, 0, 127);
                imagefill($nova, 0, 0, $transparent);
                imagesavealpha($nova, true);
                imagealphablending($nova, true);
            }
        } else {
            $nova = imagecreatetruecolor($largura, $altura);
        }

        imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, imagesx($img), imagesy($img));

        $nome = md5(uniqid(rand(), true)) . "." . $formato;

        $salvar = ($dir_saida != null) ? $dir_saida . $nome : null;

        if ($formato == 'jpg' || $formato == 'jpeg') {
            imagejpeg($nova, $salvar);
        } elseif ($formato == 'png') {
            imagepng($nova, $salvar);
        } elseif ($formato == 'gif') {
            imagegif($nova, $salvar);
        }

        imagedestroy($nova);
        imagedestroy($img);

        return $nome;
    }

    /**
     * Calculo Redimensionamento
     *
     * @param int $x
     * @param int $y
     * @param int $n_largura
     * @param int $n_altura
     *
     * @return array
     */
    private static function calcRedimensionar($x, $y, $n_largura, $n_altura) {
        if ($x > $y) {
            $largura = ($x * $n_altura) / $y;
            $altura = $n_altura;

            if ($largura < $n_largura) {
                $diferenca = $n_largura - $largura;
                $largura = $largura + $diferenca;

                $aaltura = ($y * $diferenca) / $x;
                $altura = $n_altura + $aaltura;
            }
        } else {
            $altura = ($y * $n_largura) / $x;
            $largura = $n_largura;

            if ($altura < $n_altura) {
                $diferenca = $n_altura - $altura;
                $altura = $altura + $diferenca;

                $alargura = ($x * $diferenca) / $y;
                $largura = $n_largura + $alargura;
            }
        }

        return array($largura, $altura);
    }

    /**
     * Gera miniatura da imagem
     *
     * @param string $source caminho completo da imagem ou um binario em base64
     * @param int $n_largura
     * @param int $n_altura
     * @param string $nome
     * @param string $acao_final <p>
     * string com "show_window" irá exibir a imagem direto na tela,
     * string com "return_base64" irá retornar um binario em base64
     * string do diretorio de saida íra salvar a imagem
     *
     * Se o valor for null, automaticamente a imagem será exibida na tela
     * </p>
     *
     * @return null|string
     * @throws ImagesException
     */
    public static function redimensionar($source, $n_largura, $n_altura, $nome = null, $acao_final = Images::SHOW_WINDOW) {
        $acao_final = ($acao_final == null) ? Images::SHOW_WINDOW : $acao_final;

        $img_info = self::getImageSize($source);
        $imagetype = $img_info[2];

        // Busca a largura ou a altura que não tiver sido informado
        if ($n_altura === null) $n_altura = ($n_largura * $img_info[1]) / $img_info[0];
        if ($n_largura === null) $n_largura = ($n_altura * $img_info[0]) / $img_info[1];

        // Salvar ou exibir
        $salvar = ($nome != null && $acao_final != null) ? $acao_final . $nome : null;

        //redimensiona a miniatura para o corte
        if (base64_decode($source, true) !== false) {
            $img = imagecreatefromstring(base64_decode($source));
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcRedimensionar($x, $y, $n_largura, $n_altura);
            $largura = $calc[0];
            $altura = $calc[1];

            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($img);

            // Transparencia do png
            if ($isTrueColor) {
                $nova = imagecreatetruecolor($largura, $altura);
                imagealphablending($nova, false);
                imagesavealpha($nova, true);
            } else {
                $nova = imagecreate($largura, $altura);
                imagealphablending($nova, false);
                $transparent = imagecolorallocatealpha($nova, 0, 0, 0, 127);
                imagefill($nova, 0, 0, $transparent);
                imagesavealpha($nova, true);
                imagealphablending($nova, true);
            }

            imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, $x, $y);
        } else if ($imagetype == IMAGETYPE_JPEG) {
            $img = imagecreatefromjpeg($source);
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcRedimensionar($x, $y, $n_largura, $n_altura);
            $largura = $calc[0];
            $altura = $calc[1];

            $nova = imagecreatetruecolor($largura, $altura);
            imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, $x, $y);
        } elseif ($imagetype == IMAGETYPE_PNG) {
            $img = imagecreatefrompng($source);
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcRedimensionar($x, $y, $n_largura, $n_altura);
            $largura = $calc[0];
            $altura = $calc[1];

            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($img);

            // Transparencia do png
            if ($isTrueColor) {
                $nova = imagecreatetruecolor($largura, $altura);
                imagealphablending($nova, false);
                imagesavealpha($nova, true);
            } else {
                $nova = imagecreate($largura, $altura);
                imagealphablending($nova, false);
                $transparent = imagecolorallocatealpha($nova, 0, 0, 0, 127);
                imagefill($nova, 0, 0, $transparent);
                imagesavealpha($nova, true);
                imagealphablending($nova, true);
            }

            imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, $x, $y);
        } elseif ($imagetype == IMAGETYPE_GIF) {
            $img = imagecreatefromgif($source);
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcRedimensionar($x, $y, $n_largura, $n_altura);
            $largura = $calc[0];
            $altura = $calc[1];

            $nova = imagecreatetruecolor($largura, $altura);
            imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, $x, $y);
        } else {
            throw new ImagesException("Formato inválido.");
        }

        $x = imagesx($nova);
        $y = imagesy($nova);

        $posx = ($x - $n_largura) / 2;
        $posy = ($y - $n_altura) / 2;

        // Transparencia do png
        if ($imagetype == IMAGETYPE_PNG) {
            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($nova);

            if ($isTrueColor) {
                $final = imagecreatetruecolor($n_largura, $n_altura);
                imagealphablending($final, false);
                imagesavealpha($final, true);
            } else {
                $final = imagecreate($n_largura, $n_altura);
                imagealphablending($final, false);
                $transparent = imagecolorallocatealpha($final, 0, 0, 0, 127);
                imagefill($final, 0, 0, $transparent);
                imagesavealpha($final, true);
                imagealphablending($final, true);
            }
        } else {
            $final = imagecreatetruecolor($n_largura, $n_altura);
        }

        imagecopyresampled($final, $nova, 0, 0, $posx, $posy, $n_largura, $n_altura, $n_largura, $n_altura);

        if ($acao_final == Images::RETURN_BASE64) {
            ob_start();

            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($final);
            } elseif ($imagetype == IMAGETYPE_PNG) {
                imagepng($final);
            } elseif ($imagetype == IMAGETYPE_GIF) {
                imagegif($final);
            }

            $content = ob_get_contents();
            ob_end_clean();

            return base64_encode($content);
        } else {
            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($final, $salvar);
            } elseif ($imagetype == IMAGETYPE_PNG) {
                imagepng($final, $salvar);
            } elseif ($imagetype == IMAGETYPE_GIF) {
                imagegif($final, $salvar);
            }

            imagedestroy($img);
            imagedestroy($final);
            imagedestroy($nova);

            if ($acao_final != Images::SHOW_WINDOW && $acao_final != Images::RETURN_BASE64) {
                // Retorna o nome da imagem no diretorio
                return $nome;
            } else {
                // Exibe a imagem na tela
                return null;
            }
        }
    }

    /**
     * Exibir Imagem
     *
     * @param string $imagem
     *
     * @throws ImagesException
     */
    public static function exibirImg($imagem) {
        $img_info = getimagesize($imagem);
        $imagetype = $img_info[2];

        if ($imagetype == IMAGETYPE_JPEG) {
            $img = imagecreatefromjpeg($imagem);
            imagejpeg($img);
        } elseif ($imagetype == IMAGETYPE_PNG) {
            $img = imagecreatefrompng($imagem);

            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($img);

            // Transparencia do png
            if ($isTrueColor) {
                $nova = imagecreatetruecolor($img_info[0], $img_info[1]);
                imagealphablending($nova, false);
                imagesavealpha($nova, true);
            } else {
                $nova = imagecreate($img_info[0], $img_info[1]);
                imagealphablending($nova, false);
                $transparent = imagecolorallocatealpha($nova, 0, 0, 0, 127);
                imagefill($nova, 0, 0, $transparent);
                imagesavealpha($nova, true);
                imagealphablending($nova, true);
            }

            imagecopyresampled($nova, $img, 0, 0, 0, 0, $img_info[0], $img_info[1], $img_info[0], $img_info[1]);

            imagepng($nova);
        } elseif ($imagetype == IMAGETYPE_GIF) {
            $img = imagecreatefromgif($imagem);
            imagegif($img);
        } else {
            throw new ImagesException("Formato inválido.");
        }

        imagedestroy($img);
    }

    /**
     * Crop
     *
     * @param string $source caminho completo da imagem ou um binario em base64
     * @param int $x Posição x crop
     * @param int $y Posição y crop
     * @param int $w Largura da imagem no crop
     * @param int $h Altura da imagem no crop
     * @param string $acao_final <p>
     * string com "show_window" irá exibir a imagem direto na tela,
     * string com "return_base64" irá retornar um binario em base64
     * string do diretorio de saida íra salvar a imagem
     *
     * Se o valor for null, automaticamente a imagem será exibida na tela
     * </p>
     *
     * @return string
     * @throws ImagesException
     */
    public static function crop($source, $x, $y, $w, $h, $acao_final = Images::SHOW_WINDOW) {
        $acao_final = ($acao_final == null) ? Images::SHOW_WINDOW : $acao_final;

        $img_info = getimagesize($source);
        $imagetype = $img_info[2];

        if (base64_decode($source, true) != false) {
            $img = imagecreatefromstring(base64_decode($source));
        } else if ($imagetype == IMAGETYPE_JPEG) {
            $img = imagecreatefromjpeg($source);
        } elseif ($imagetype == IMAGETYPE_PNG) {
            $img = imagecreatefrompng($source);
        } elseif ($imagetype == IMAGETYPE_GIF) {
            $img = imagecreatefromgif($source);
        } else {
            throw new ImagesException("Formato inválido.");
        }

        if ($imagetype == IMAGETYPE_PNG) {
            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($img);

            // Transparencia do png
            if ($isTrueColor) {
                $nova = imagecreatetruecolor($w, $h);
                imagealphablending($nova, false);
                imagesavealpha($nova, true);
            } else {
                $nova = imagecreate($w, $h);
                imagealphablending($nova, false);
                $transparent = imagecolorallocatealpha($nova, 0, 0, 0, 127);
                imagefill($nova, 0, 0, $transparent);
                imagesavealpha($nova, true);
                imagealphablending($nova, true);
            }
        } else {
            $nova = imagecreatetruecolor($w, $h);
        }

        imagecopyresampled($nova, $img, 0, 0, $x, $y, $w, $h, $w, $h);

        $nome = "";
        if ($acao_final != Images::SHOW_WINDOW && $acao_final != Images::RETURN_BASE64) {
            $nome = md5(uniqid(rand(), true)) . "." . $imagetype;
            $salvar = $acao_final . $nome;
        } else {
            $salvar = null;
        }

        // Se true retorna o resource da imagem
        if ($acao_final === Images::RETURN_BASE64) {
            ob_start();

            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($nova);
            } elseif ($imagetype == IMAGETYPE_PNG) {
                imagepng($nova);
            } elseif ($imagetype == IMAGETYPE_GIF) {
                imagegif($nova);
            }

            $content = ob_get_contents();
            ob_end_clean();

            return base64_encode($content);
        } else {
            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($nova, $salvar);
            } elseif ($imagetype == IMAGETYPE_PNG) {
                imagepng($nova, $salvar);
            } elseif ($imagetype == IMAGETYPE_GIF) {
                imagegif($nova, $salvar);
            }

            imagedestroy($nova);
            imagedestroy($img);

            if ($acao_final != Images::SHOW_WINDOW && $acao_final != Images::RETURN_BASE64) {
                // Retorna o nome da imagem no diretorio
                return $nome;
            } else {
                // Exibe a imagem na tela
                return null;
            }
        }
    }

    /**
     * Redimensionamento de Imagem
     *
     * @param int $real_width Largura da imagem no crop
     * @param int $real_height Altura da imagem no crop
     * @param int $pos_w Largura desejada
     * @param int $pos_h Altura desejada
     * @param int $pos_x Posição x do crop
     * @param int $pos_y Posição y do crop
     * @param int $tela_width Largura durante o recorte
     * @param int $tela_height Altura durante o recorte
     * @param bool $inverter Reverter redimensionamento
     *
     * @return array
     */
    public static function redimensionamento($real_width, $real_height, $pos_w, $pos_h, $pos_x, $pos_y, $tela_width, $tela_height, $inverter = false) {
        $real = array();

        if (!$inverter) {
            $real[0] = ($pos_x * $real_width) / $tela_width;
            $real[1] = ($pos_y * $real_height) / $tela_height;
            $real[2] = ($pos_w * $real_width) / $tela_width;
            $real[3] = ($pos_h * $real_height) / $tela_height;
        } else {
            $real[0] = ($tela_width * $pos_x) / $real_width;
            $real[1] = ($tela_height * $pos_y) / $real_height;
            $real[2] = ($tela_width * ($pos_x + $pos_w)) / $real_width;
            $real[3] = ($tela_height * ($pos_y + $pos_h)) / $real_height;
        }

        return $real;
    }

    /**
     * Pega os dados de uma imagem dependendo da origem
     *
     * @param $source
     *
     * @return array|bool
     */
    private static function getImageSize($source) {
        if (base64_decode($source, true) === false) {
            return getimagesize($source);
        } else {
            $uri = 'data://application/octet-stream;base64,' . $source;
            return getimagesize($uri);
        }
    }
}