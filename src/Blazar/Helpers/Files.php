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
 * Classe de recursos para auxiliar com arquivos e Diretórios
 */
class Files {

    /*
     * Locais para a escrita no arquivo
     */
    const WRITE_OVERWRITE = "overwrite";
    const WRITE_APPEND = "append";
    const WRITE_PREPEND = "prepend";

    private static $unidades_medida = ['KB', 'MB', 'GB', 'TB'];

    /**
     * Envio de arquivos
     *
     * @param array $file Um array no padrão da global $_FILE['*'].
     * @param string $dir_out diretório de destino do arquivo.
     * @param array $extensions Se informado só permito arquivos com as extensões informadas.<br>
     * As extensões devem estar em minusculo.
     *
     * @return string|null Retorna o nome do arquivo ou null caso ocorra uma falha
     */
    public static function upload(array $file, string $dir_out, array $extensions = []): ?string {
        $tempFile = $file['tmp_name'];

        // Pega informações do arquivo
        $fileParts = pathinfo($file['name']);

        $new_name = md5(uniqid(rand(), true)) . "." . $fileParts['extension'];
        $targetFile = $dir_out . $new_name;

        if (count($extensions) == 0 || in_array(strtolower($fileParts['extension']), $extensions)) {
            // Move o arquivo para o diretório definitivo
            if (move_uploaded_file($tempFile, $targetFile)) {
                return $new_name;
            } else return null;
        } else {
            return null;
        }
    }

    /**
     * Escreve em arquivo
     *
     * @param string $file Caminho ate o arquivo.
     * @param string $text - Texto para adicionar.
     * @param string $insert_in_position Posição onde o texto será inserido prepend, append ou overwrite.
     * @return bool
     */
    public static function write(string $file, string $text, string $insert_in_position = self::WRITE_OVERWRITE): bool {
        // Adiciona texto ao inicio ou ao final do arquivo
        if ($insert_in_position === self::WRITE_PREPEND && file_exists($file)) {
            $ln = self::read($file);
            if ($ln !== null) $text = $text . $ln;
            else return false;
        } elseif ($insert_in_position === self::WRITE_APPEND && file_exists($file)) {
            $ln = self::read($file);
            if ($ln !== null) $text = $ln . $text;
            else return false;
        }

        if (($fp = fopen($file, "w"))) {
            fwrite($fp, $text);
            fclose($fp);

            return true;
        } else return false;
    }

    /**
     * Ler conteudo de um arquivo
     *
     * @param string $file Caminho ate o arquivo.
     *
     * @return string|null Retorna o conteudo ou null caso ocorra algum erro
     */
    public static function read(string $file): ?string {
        $linha = "";

        if (file_exists($file)) {
            // Quando o fopen falha ele retorna false ao inves do resource
            // Este if testa se o fopen retornou os dados corretos para evitar erros
            if (($ponteiro = fopen($file, "r"))) {
                while (!feof($ponteiro)) {
                    $linha .= fgets($ponteiro, 4096);
                }

                fclose($ponteiro);
            } else {
                return null;
            }
        } else {
            return null;
        }

        return $linha;
    }

    /**
     * Calcula tamanho da pasta
     *
     * @param string $dir_path Caminho do diretório.
     *
     * @return int
     */
    public static function folderSize(string $dir_path): int {
        $total_size = 0;
        $files = scandir($dir_path);

        foreach ($files as $t) {
            if (is_dir(rtrim($dir_path, '/') . '/' . $t)) {
                if ($t !== "." && $t !== "..") {
                    $size = self::folderSize(rtrim($dir_path, '/') . '/' . $t);

                    $total_size += $size;
                }
            } else {
                $size = filesize(rtrim($dir_path, '/') . '/' . $t);
                $total_size += $size;
            }
        }

        return $total_size;
    }

    /**
     * Retorna o tamanho do arquivo com a unidade de medida
     *
     * @param int|string $base_medida O tamanho que será checado.<br>
     *  Pode ser um inteiro com o tamanho ou a string com o caminho para um arquivo.
     * @param bool $separar_medida Se true retorna o tamanho e a unidade de medida separados em um array.
     * @return array|string
     * @throws FilesException
     */
    public static function fileSizeReal($base_medida, bool $separar_medida = false) {
        if (is_int($base_medida)) {
            $tamanho = $base_medida;
        } else if (file_exists($base_medida)) {
            $tamanho = filesize($base_medida);
        } else {
            throw new FilesException("A base para medida deve ser um inteiro ou a string com o caminho para um arquivo.");
        }

        /* Se for menor que 1KB arredonda para 1KB */
        if ($tamanho < 999) {
            $tamanho = 1000;
        }

        for ($i = 0; $tamanho > 999; $i++) {
            $tamanho /= 1024;
        }

        $dados = [round($tamanho), self::$unidades_medida[$i - 1]];

        if ($separar_medida) return $dados;
        else return $dados[0] . $dados[1];
    }

    /**
     * Verifica se o tamanho do arquivo é valido
     *
     * O tamanho minimo para verificar é 1KB
     *
     * @param int|string $max_filesize O tamanho maximo permitido com a unidade de medida. Ex: "10MB".<br>
     *  Se a unidade de medida não for passada a unidade usada será BYTE.
     * @param int|string $base_checar O tamanho que será checado.<br>
     *  Pode ser um inteiro com o tamanho em BYTE ou uma string com o caminho para um arquivo.
     * @return bool
     * @throws FilesException
     */
    public static function validFileSize($max_filesize, $base_checar): bool {
        // Remove espaços
        $max_filesize = str_replace(" ", "", $max_filesize);
        // Força a unidade de medida para maiuscula
        $max_filesize = strtoupper($max_filesize);

        // Verifica se vai usar um inteiro já informado ou se deve pegar direto no arquivo
        if (is_int($base_checar)) {
            $tamanho = $base_checar;
        } else if (file_exists($base_checar)) {
            $tamanho = filesize($base_checar);
        } else {
            throw new FilesException("A base para medida deve ser um inteiro ou a string com o caminho para um arquivo.");
        }

        $i = 0;
        // Percorre as unidade possíveis para obter a posição dela na lista
        while ($i < count(self::$unidades_medida)) {
            $unidade = self::$unidades_medida[$i];

            if (substr_count($max_filesize, $unidade) > 0) {
                $max_filesize = str_replace($unidade, "", $max_filesize);
                break;
            }

            $i++;
        }

        // Verifica se o padrão do tamanho é valido
        if (!is_numeric($max_filesize)) {
            throw new FilesException("Formato do \"max_filesize\" inválido.");
        }

        // Se encontrar a unidade transforma em byte, se não mantem.
        if ($i < count(self::$unidades_medida)) {
            // Transforma a unidade passada em byte
            $max_filesize = $max_filesize * pow(1024, $i + 1);
        }

        return ($tamanho <= $max_filesize);
    }

    /**
     * Combina varios caminhos
     *
     * @param string ...$args Caminhos para unir
     * @return string
     */
    public static function pathJoin(string ...$args): string {
        $starts_with_bar = false;
        $paths = [];

        // Quebra cada argumento pela barra
        foreach ($args as $arg) {
            $arg = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $arg);

            if (count($paths) == 0 && StrRes::startsWith($arg, DIRECTORY_SEPARATOR))
                $starts_with_bar = true;

            $subitems = array_filter(explode(DIRECTORY_SEPARATOR, $arg), 'strlen');
            $paths = array_merge($paths, $subitems);
        }

        $final = [];
        // Monta o caminho final
        foreach ($paths as $path) {
            if ('.' == $path) continue;

            if ('..' == $path) array_pop($final);
            else $final[] = $path;
        }

        return ($starts_with_bar ? DIRECTORY_SEPARATOR : "") . implode(DIRECTORY_SEPARATOR, $final);
    }

    /**
     * @deprecated Use o metodo self::write
     * @param $arquivo
     * @param $texto
     * @param null $local
     * @return bool
     */
    public static function escrita($arquivo, $texto, $local = null) {
        return self::write($arquivo, $texto, $local);
    }

    /**
     * @deprecated Use o metodo self::read
     * @param $arquivo
     * @return String
     */
    public static function leitura($arquivo) {
        return self::read($arquivo);
    }
}