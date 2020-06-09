<?php

namespace Blazar\Component\TypeRes;

use Blazar\Component\Cli\CliInterfaceException;
use Blazar\Component\TypeRes\ArrayRes;

class JSONRes {

    /**
     * Armazena dados em arquivos .json
     *
     * @param string $filename Path para o arquivo
     * @param string $index_route Rota de indices. Ex: "nivel/nivel2"
     * @param string|null $value Valor para o indice
     *
     * @throws CliInterfaceException
     */
    public static function save(string $filename, string $index_route, ?string $value = null) {
        // Pega dados de um arquivo existente
        $dados = [];
        if (file_exists($filename)) {
            $json_string = file_get_contents($filename);
            $dados = json_decode($json_string, true);

            if ($dados === null) {
                throw new CliInterfaceException("O conteúdo do arquivo \"$filename\" não é um JSON.");
            }
        }

        // Adiciona novos indices
        $dados = ArrayRes::insertInRoute($dados, $index_route, $value);

        // Transforma em JSON e ajusta indentação para apenas 2 espaços
        $str = json_encode($dados, JSON_PRETTY_PRINT);
        $str = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $str);

        // Cria o diretório se ele não existir
        $dir_file = dirname($filename);
        if (!is_dir($dir_file)) {
            mkdir($dir_file, 0777, true);
            shell_exec("chown www-data:www-data $dir_file");
        }

        // Salva o novo arquivo
        file_put_contents($filename, $str);
        shell_exec("chown www-data:www-data $filename");
    }

    /**
     * Pega um dado em arquivos .json
     *
     * @param string $filename Path para o arquivo
     * @param string $index_route Rota de indices. Ex: "nivel/nivel2"
     *
     * @return string
     * @throws CliInterfaceException
     */
    public static function read(string $filename, string $index_route = "/") {
        if (file_exists($filename)) {
            $json_string = file_get_contents($filename);
            $dados = json_decode($json_string, true);

            if ($dados === null) {
                throw new CliInterfaceException("O conteúdo do arquivo \"$filename\" não é um JSON.");
            }

            // Pega o valor seguindo a rota informada
            $dados = ArrayRes::route($dados, $index_route);

            // Transforma em JSON se for um array
            if (is_array($dados)) {
                $dados = json_encode($dados, JSON_PRETTY_PRINT);
                $dados = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $dados);
            }

            return "$dados";
        } else {
            throw new CliInterfaceException("O arquivo \"$filename\" não foi encontrado.");
        }
    }

}
