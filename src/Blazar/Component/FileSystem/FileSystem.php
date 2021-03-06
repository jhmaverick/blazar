<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\FileSystem;

use Blazar\Component\TypeRes\StrRes;

/**
 * Classe de recursos para auxiliar com arquivos e Diretórios.
 */
class FileSystem {
    const WRITE_APPEND = 'append';

    private static $unidades_medida = ['KB', 'MB', 'GB', 'TB'];

    /**
     * Envio de arquivos.
     *
     * @param array $file Um array no padrão da global $_FILE['*'].
     * @param string $dir_out diretório de destino do arquivo.
     * @param array $extensions Se informado só permito arquivos com as extensões informadas.<br>
     * As extensões devem estar em minusculo.
     *
     * @return string|null Retorna o nome do arquivo ou null caso ocorra uma falha
     * @throws FileException
     */
    public static function upload(array $file, string $dir_out, array $extensions = []): ?string {
        $tempFile = $file['tmp_name'];

        // Pega informações do arquivo
        $fileParts = pathinfo($file['name']);

        $new_name = md5(uniqid(rand(), true)) . '.' . $fileParts['extension'];
        $targetFile = $dir_out . $new_name;

        if (count($extensions) == 0 || in_array(strtolower($fileParts['extension']), $extensions)) {
            // Cria o diretório caso ele não exista
            if (!file_exists($dir_out)) {
                @mkdir($dir_out, 0777, true);
            }
            if (!is_dir($dir_out)) {
                throw new FileException("Não foi possível criar o diretório \"$dir_out\".");
            }

            // Move o arquivo para o diretório definitivo
            if (move_uploaded_file($tempFile, $targetFile)) {
                return $new_name;
            } else {
                throw new FileException('Problemas ao mover arquivo para o diretório desejado.');
            }
        } else {
            throw new FileException('Formato de arquivo inválido.');
        }
    }

    /**
     * Escreve em arquivo.
     *
     * @deprecated usar file_put_contents
     *
     * @param string $file Caminho ate o arquivo.
     * @param string $text - Texto para adicionar.
     * @param string $insert_in Posição onde o texto será inserido append ou overwrite.
     *
     * @return bool
     */
    public static function write(string $file, string $text, string $insert_in = null) {
        return file_put_contents($file, $text, ($insert_in == self::WRITE_APPEND ? FILE_APPEND : null));
    }

    /**
     * Ler conteúdo de um arquivo.
     *
     * @deprecated usar file_get_contents
     *
     * @param string $file Caminho ate o arquivo.
     *
     * @return string|null Retorna o conteudo ou null caso ocorra algum erro
     */
    public static function read(string $file) {
        return @file_get_contents($file);
    }

    /**
     * Calcula tamanho da pasta.
     *
     * @param string $dir_path Caminho do diretório.
     *
     * @return int
     */
    public static function folderSize(string $dir_path): int {
        $total_size = 0;
        $files = scandir($dir_path);

        foreach ($files as $t) {
            if (is_dir(rtrim($dir_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $t)) {
                if ($t !== '.' && $t !== '..') {
                    $size = self::folderSize(rtrim($dir_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $t);

                    $total_size += $size;
                }
            } else {
                $size = filesize(rtrim($dir_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $t);
                $total_size += $size;
            }
        }

        return $total_size;
    }

    /**
     * Retorna o tamanho do arquivo com a unidade de medida.
     *
     * @param int|string $base_medida O tamanho que será checado.<br>
     *  Pode ser um inteiro com o tamanho ou a string com o caminho para um arquivo.
     * @param bool $separar_medida Se true retorna o tamanho e a unidade de medida separados em um array.
     *
     * @return array|string
     * @throws FileException
     */
    public static function fileSizeReal($base_medida, bool $separar_medida = false) {
        if (is_int($base_medida)) {
            $tamanho = $base_medida;
        } elseif (file_exists($base_medida)) {
            $tamanho = filesize($base_medida);
        } else {
            throw new FileException('A base para medida deve ser um inteiro ou a string com o caminho para um arquivo.');
        }

        /* Se for menor que 1KB arredonda para 1KB */
        if ($tamanho < 999) {
            $tamanho = 1000;
        }

        for ($i = 0; $tamanho > 999; $i++) {
            $tamanho /= 1024;
        }

        $dados = [round($tamanho), self::$unidades_medida[$i - 1]];

        if ($separar_medida) {
            return $dados;
        } else {
            return $dados[0] . $dados[1];
        }
    }

    /**
     * Verifica se o tamanho do arquivo é valido.
     *
     * O tamanho minimo para verificar é 1KB
     *
     * @param int|string $max_filesize O tamanho maximo permitido com a unidade de medida. Ex: "10MB".<br>
     *  Se a unidade de medida não for passada a unidade usada será BYTE.
     * @param int|string $base_checar O tamanho que será checado.<br>
     *  Pode ser um inteiro com o tamanho em BYTE ou uma string com o caminho para um arquivo.
     *
     * @return bool
     * @throws FileException
     */
    public static function validFileSize($max_filesize, $base_checar): bool {
        // Remove espaços
        $max_filesize = str_replace(' ', '', $max_filesize);
        // Força a unidade de medida para maiuscula
        $max_filesize = strtoupper($max_filesize);

        // Verifica se vai usar um inteiro já informado ou se deve pegar direto no arquivo
        if (is_int($base_checar)) {
            $tamanho = $base_checar;
        } elseif (file_exists($base_checar)) {
            $tamanho = filesize($base_checar);
        } else {
            throw new FileException('A base para medida deve ser um inteiro ou a string com o caminho para um arquivo.');
        }

        $i = 0;
        // Percorre as unidade possíveis para obter a posição dela na lista
        while ($i < count(self::$unidades_medida)) {
            $unidade = self::$unidades_medida[$i];

            if (substr_count($max_filesize, $unidade) > 0) {
                $max_filesize = str_replace($unidade, '', $max_filesize);

                break;
            }

            $i++;
        }

        // Verifica se o padrão do tamanho é valido
        if (!is_numeric($max_filesize)) {
            throw new FileException('Formato do "max_filesize" inválido.');
        }

        // Se encontrar a unidade transforma em byte, se não mantem.
        if ($i < count(self::$unidades_medida)) {
            // Transforma a unidade passada em byte
            $max_filesize = $max_filesize * pow(1024, $i + 1);
        }

        return ($tamanho <= $max_filesize);
    }

    /**
     * Combina vários caminhos.
     *
     * Este método não verifica se o caminho final existe.
     * Este método não verifica se o caminho final retornado existe.
     *
     * @param string $path1 Primeiro caminho
     * @param string[] $paths n caminhos
     *
     * @return string
     */
    public static function pathJoin(string $path1, string ...$paths): string {
        array_unshift($paths, $path1);
        $starts_with_bar = false;
        $list = [];

        // Quebra cada argumento pela barra
        foreach ($paths as $arg) {
            $arg = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $arg);

            if (count($list) == 0 && StrRes::startsWith($arg, DIRECTORY_SEPARATOR)) {
                $starts_with_bar = true;
            }

            $subitems = array_filter(explode(DIRECTORY_SEPARATOR, $arg), 'strlen');
            $list = array_merge($list, $subitems);
        }

        $final = [];
        // Monta o caminho final
        foreach ($list as $index) {
            if ('.' == $index) {
                continue;
            }

            if ('..' == $index) {
                array_pop($final);
            } else {
                $final[] = $index;
            }
        }

        return ($starts_with_bar ? DIRECTORY_SEPARATOR : '') . implode(DIRECTORY_SEPARATOR, $final);
    }

    /**
     * Combina vários caminhos mantendo o último diretório da lista que estiver iniciando do root do sistema.
     *
     * Se nenhuma das partes estiver iniciando do root o diretório do arquivo que requisitou será o inicio do path
     * final.<br>
     * Este método não verifica se o caminho final retornado existe.
     *
     * @param string $path1 Primeiro caminho
     * @param string[] $paths n caminhos
     *
     * @return string
     */
    public static function pathResolve(string $path1, string ...$paths): string {
        // Adiciona o diretório do arquivo que chamou o método como o primeiro na fila de diretórios
        $trace = debug_backtrace();
        $dir = dirname($trace[0]['file']);

        array_unshift($paths, $dir, $path1);

        $final = [];
        for ($i = 0; $i < count($paths); $i++) {
            // Verifica se o índice inicia do root do sistema
            if (StrRes::startsWith($paths[$i], '/') ||
                substr($paths[$i], 1, 2) == ':\\' ||
                substr($paths[$i], 1, 2) == ':/'
            ) {
                $final = [$paths[$i]];
            } else {
                $final[] = $paths[$i];
            }
        }

        return call_user_func_array([__CLASS__, 'pathJoin'], $final);
    }
}