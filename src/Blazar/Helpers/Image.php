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
class Image {
    /**#@+
     * Tipos de resultado para imagens
     */
    const TP_RESULT_SHOW_IMG = 1;
    const TP_RESULT_SAVE_IMG = 2;
    const TP_RESULT_RETURN_BASE64 = 3;
    /**#@-*/

    /**
     * Upload de imagens
     *
     * @param array $file
     * @param string|null $output_dir - O diretório de saida(se null exibe diretamente no navegador)
     * @param int $width
     * @param int $height
     * @param bool $keep_shorter - Manter tamanho se forem menores que o informado
     *
     * @return string
     * @throws ImageException
     */
    public static function upload(array $file, string $output_dir, ?int $width = null, ?int $height = null, bool $keep_shorter = true) {
        $fileParts = pathinfo($file['name']);
        $formato = strtolower($fileParts['extension']);

        if ($formato == 'jpg' || $formato == 'jpeg') $img = imagecreatefromjpeg($file['tmp_name']);
        else if ($formato == 'png') $img = imagecreatefrompng($file['tmp_name']);
        else if ($formato == 'gif') $img = imagecreatefromgif($file['tmp_name']);
        else throw new ImageException("Formato inválido.");

        try {
            $name = File::upload($file, $output_dir, ["jpg", "jpeg", "png", "gif"]);
            $file_name = File::pathJoin($output_dir, $name);

            // Caso manter menor seja verdadeiro e tamanho tambem seja menor ou
            // se largura e altura seja menor aplica tamanhos padrões da imagem
            if (($keep_shorter && imagesx($img) < $width && imagesy($img) < $height) || ($width == null && $height == null)) {
                $width = imagesx($img);
                $height = imagesy($img);
            }

            // Evita que imagem seja maior que o permitido pelo sistema
            if ($width != null && $width > Manifest::config("max_img_width"))
                $width = Manifest::config("max_img_width");
            if ($height != null && $height > Manifest::config("max_img_height"))
                $height = Manifest::config("max_img_height");

            // Calcula dimensão que estiver faltando
            if ($width != null && (imagesx($img) >= imagesy($img) || $height == null))
                $height = (imagesy($img) * $width) / imagesx($img);
            else if ($height != null && (imagesy($img) > imagesx($img) || $width == null))
                $width = (imagesx($img) * $height) / imagesy($img);

            // Faz o redimensionamento
            self::resize($file_name, $width, $height, self::TP_RESULT_SAVE_IMG, $file_name);

            return $name;
        } catch (FileException $e) {
            if (isset($file_name)) @unlink($file_name);
            throw new ImageException($e->getMessage());
        }
    }

    /**
     * Redimensionar imagem
     *
     * @param string $source caminho completo da imagem ou um binario em base64
     * @param int $width Largura para a imagem.
     * @param int $height Altura para a imagem.
     * @param int $type_result <p>
     * TP_RESULT_SHOW_IMG irá exibir a imagem direto na tela.<br>
     * TP_RESULT_RETURN_BASE64 irá retornar um binario em base64.<br>
     * TP_RESULT_SHOW_IMG do diretório(sem o nome) de saida íra salvar a imagem.<br>
     * <br>
     * Se o valor for null, automaticamente a imagem será exibida na tela.
     * </p>
     * @param string $file_name Caminho com o nome do arquivo.
     *
     * @return string|null retorna uma string em caso de retorno em base64
     * @throws ImageException
     */
    public static function resize(string $source,
                                  ?int $width = null,
                                  ?int $height = null,
                                  int $type_result = self::TP_RESULT_SHOW_IMG,
                                  ?string $file_name = null
    ) {
        if ($width === null && $height === null)
            throw new ImageException('"width" ou "height" deve ser informado.');
        if ($file_name === null && $type_result === self::TP_RESULT_SAVE_IMG)
            throw new ImageException('"file_name" deve ser informado.');

        $img_info = self::getImageSize($source);
        $imagetype = $img_info[2];

        // Busca a largura ou a altura que não tiver sido informado
        if ($height === null) $height = ($width * $img_info[1]) / $img_info[0];
        if ($width === null) $width = ($height * $img_info[0]) / $img_info[1];

        //redimensiona a miniatura para o corte
        if (base64_decode($source, true) !== false) {
            $img = imagecreatefromstring(base64_decode($source));
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcResize($x, $y, $width, $height);
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

            $calc = self::calcResize($x, $y, $width, $height);
            $largura = $calc[0];
            $altura = $calc[1];

            $nova = imagecreatetruecolor($largura, $altura);
            imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, $x, $y);
        } else if ($imagetype == IMAGETYPE_PNG) {
            $img = imagecreatefrompng($source);
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcResize($x, $y, $width, $height);
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
        } else if ($imagetype == IMAGETYPE_GIF) {
            $img = imagecreatefromgif($source);
            $x = imagesx($img);
            $y = imagesy($img);

            $calc = self::calcResize($x, $y, $width, $height);
            $largura = $calc[0];
            $altura = $calc[1];

            $nova = imagecreatetruecolor($largura, $altura);
            imagecopyresampled($nova, $img, 0, 0, 0, 0, $largura, $altura, $x, $y);
        } else {
            throw new ImageException("Formato inválido.");
        }

        $x = imagesx($nova);
        $y = imagesy($nova);

        $posx = ($x - $width) / 2;
        $posy = ($y - $height) / 2;

        // Transparencia do png
        if ($imagetype == IMAGETYPE_PNG) {
            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($nova);

            if ($isTrueColor) {
                $final = imagecreatetruecolor($width, $height);
                imagealphablending($final, false);
                imagesavealpha($final, true);
            } else {
                $final = imagecreate($width, $height);
                imagealphablending($final, false);
                $transparent = imagecolorallocatealpha($final, 0, 0, 0, 127);
                imagefill($final, 0, 0, $transparent);
                imagesavealpha($final, true);
                imagealphablending($final, true);
            }
        } else {
            $final = imagecreatetruecolor($width, $height);
        }

        imagecopyresampled($final, $nova, 0, 0, $posx, $posy, $width, $height, $width, $height);

        if ($type_result == self::TP_RESULT_RETURN_BASE64) {
            ob_start();

            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($final);
            } else if ($imagetype == IMAGETYPE_PNG) {
                imagepng($final);
            } else if ($imagetype == IMAGETYPE_GIF) {
                imagegif($final);
            }

            $content = ob_get_contents();
            ob_end_clean();

            return base64_encode($content);
        } else {
            // Salvar ou exibir
            $salvar = ($file_name !== null && $type_result === self::TP_RESULT_SAVE_IMG) ? $file_name : null;

            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($final, $salvar);
            } else if ($imagetype == IMAGETYPE_PNG) {
                imagepng($final, $salvar);
            } else if ($imagetype == IMAGETYPE_GIF) {
                imagegif($final, $salvar);
            }

            imagedestroy($img);
            imagedestroy($final);
            imagedestroy($nova);

            return null;
        }
    }

    /**
     * Crop
     *
     * @param string $source caminho completo da imagem ou um binario em base64
     * @param int $pos_x Posição x crop
     * @param int $pos_y Posição y crop
     * @param int $width Largura da imagem no crop
     * @param int $height Altura da imagem no crop
     * @param int $type_result <p>
     * TP_RESULT_SHOW_IMG irá exibir a imagem direto na tela.<br>
     * TP_RESULT_RETURN_BASE64 irá retornar um binario em base64.<br>
     * TP_RESULT_SHOW_IMG do diretório(sem o nome) de saida íra salvar a imagem.<br>
     * <br>
     * Se o valor for null, automaticamente a imagem será exibida na tela.
     * </p>
     * @param string $file_name Caminho com o nome do arquivo.
     *
     * @return string|null
     * @throws ImageException
     */
    public static function crop(string $source,
                                int $pos_x,
                                int $pos_y,
                                int $width,
                                int $height,
                                int $type_result = self::TP_RESULT_SHOW_IMG,
                                ?string $file_name = null
    ) {
        if ($file_name === null && $type_result === self::TP_RESULT_SAVE_IMG)
            throw new ImageException('"file_name" deve ser informado.');

        $img_info = self::getImageSize($source);
        $imagetype = $img_info[2];

        if (base64_decode($source, true) != false) {
            $img = imagecreatefromstring(base64_decode($source));
        } else if ($imagetype == IMAGETYPE_JPEG) {
            $img = imagecreatefromjpeg($source);
        } else if ($imagetype == IMAGETYPE_PNG) {
            $img = imagecreatefrompng($source);
        } else if ($imagetype == IMAGETYPE_GIF) {
            $img = imagecreatefromgif($source);
        } else {
            throw new ImageException("Formato inválido.");
        }

        if ($imagetype == IMAGETYPE_PNG) {
            // Verifica se a imagem tem transparencia
            $isTrueColor = imageistruecolor($img);

            // Transparencia do png
            if ($isTrueColor) {
                $nova = imagecreatetruecolor($width, $height);
                imagealphablending($nova, false);
                imagesavealpha($nova, true);
            } else {
                $nova = imagecreate($width, $height);
                imagealphablending($nova, false);
                $transparent = imagecolorallocatealpha($nova, 0, 0, 0, 127);
                imagefill($nova, 0, 0, $transparent);
                imagesavealpha($nova, true);
                imagealphablending($nova, true);
            }
        } else {
            $nova = imagecreatetruecolor($width, $height);
        }

        imagecopyresampled($nova, $img, 0, 0, $pos_x, $pos_y, $width, $height, $width, $height);

        // Se true retorna o resource da imagem
        if ($type_result === self::TP_RESULT_RETURN_BASE64) {
            ob_start();

            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($nova);
            } else if ($imagetype == IMAGETYPE_PNG) {
                imagepng($nova);
            } else if ($imagetype == IMAGETYPE_GIF) {
                imagegif($nova);
            }

            $content = ob_get_contents();
            ob_end_clean();

            return base64_encode($content);
        } else {
            // Salvar ou exibir
            $salvar = ($file_name !== null && $type_result === self::TP_RESULT_SAVE_IMG) ? $file_name : null;

            if ($imagetype == IMAGETYPE_JPEG) {
                imagejpeg($nova, $salvar);
            } else if ($imagetype == IMAGETYPE_PNG) {
                imagepng($nova, $salvar);
            } else if ($imagetype == IMAGETYPE_GIF) {
                imagegif($nova, $salvar);
            }

            imagedestroy($nova);
            imagedestroy($img);

            return null;
        }
    }

    /**
     * Faz a saida de uma imagem
     *
     * Se a altura ou largura informados não forem proporcionais as partes restantes serão cortadas
     *
     * @param string $source um local predefinido para o local da imagem ou um base64
     * @param int|null $width Largura para exibir a imagem
     * @param int|null $height Altura para a exibir imagem
     * @param int|null $pos_x Posição x para recorte
     * @param int|null $pos_y Posição y para recorte
     * @param int|null $crop_width Gerar miniatura do recorte
     * @param int|null $crop_height Gerar miniatura do recorte
     *
     * @throws ImageException
     */
    public function output(string $source,
                           int $width = null,
                           int $height = null,
                           int $pos_x = null,
                           int $pos_y = null,
                           int $crop_width = null,
                           int $crop_height = null
    ) {
        if ($width === null && $height === null)
            throw new ImageException('"width" ou "height" deve ser informado.');

        $img_type = self::getImageSize($source)[2];

        // Verifica o formato da imagem
        if ($img_type === IMAGETYPE_GIF) header('Content-Type: image/gif');
        else if ($img_type === IMAGETYPE_JPEG) header('Content-Type: image/jpg');
        else if ($img_type === IMAGETYPE_PNG) header('Content-Type: image/png');
        else throw new ImageException("Mídia não encontrada.");

        if (($width !== null || $height !== null) && $pos_x === null && $pos_y === null) {
            // Redimensionar
            $w = $width ?? null;
            $h = $height ?? null;

            self::resize($source, $w, $h);
        } else if ($width !== null && $height !== null && $pos_x !== null && $pos_y !== null) {
            // Crop
            if ($crop_width !== null || $crop_height !== null) {
                // Faz o crop e gera a miniatura
                $result = self::crop($source, $pos_x, $pos_y, $width, $height, self::TP_RESULT_RETURN_BASE64);
                self::resize($result, $crop_width, $crop_height);
            } else {
                // Crop sem miniatura
                self::crop($source, $pos_x, $pos_y, $width, $height);
            }
        } else {
            // Exibe sem alterações
            if (base64_decode($source, true) !== false) {
                // Exibe em base64
                echo base64_decode($source);
            } else {
                // Imagem de um caminho
                readfile($source);
            }
        }
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
    private static function calcResize($x, $y, $n_largura, $n_altura) {
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

        return [$largura, $altura];
    }
}