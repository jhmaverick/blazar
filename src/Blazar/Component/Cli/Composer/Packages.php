<?php


namespace Blazar\Component\Cli\Composer;


use Blazar\Component\Cli\CliInterfaceException;

class Packages {

    /**
     * Aplica os dados dos repositórios em um arquivo JSON
     *
     * @param string $source_dir Diretório para procurar os repositórios.
     *
     * @return array
     * @throws CliInterfaceException
     */
    public static function findRepos(string $source_dir) {
        if (!is_dir($source_dir)) {
            throw new CliInterfaceException("Diretório \"$source_dir\" não existe.");
        }

        $source_dir = rtrim($source_dir, "/");

        $list = [];

        if (is_dir($source_dir)) {
            if (file_exists("$source_dir/composer.json")) {
                $composer_cotent = file_get_contents("$source_dir/composer.json");
                $composer_data = json_decode($composer_cotent);

                // Verifica se uma versão foi definida
                $composer_data->version = $composer_data->version ?? "dev-master";
                // Aplica o repositório local
                $composer_data->dist = (object)[
                    "type" => "path",
                    "url" => "$source_dir"
                ];

                // Adiciona pacote na lista
                $list[$composer_data->name] = [
                    $composer_data->version => $composer_data
                ];
            }

            foreach (scandir($source_dir) as $file) {
                if ('.' === $file || '..' === $file) continue;

                // Adiciona desde que seja um diretório e não seja um atalho
                if (is_dir("$source_dir/$file")
                    && !is_link("$source_dir/$file") // Links simbolicos do linux
                    && !self::isSymbolicLink("$source_dir/$file") // Atalhos do windows
                    && "$source_dir/$file" != "$source_dir/vendor"
                ) {
                    $list = array_merge($list, self::findRepos("$source_dir/$file"));
                }
            }
        }

        return $list;
    }

    /**
     * Aplica os dados dos repositórios em um arquivo JSON
     *
     * @param string $project_src Diretório para procurar os repositórios.
     * @param string $composer_packages_json Caminho completo para o arquivo que recebera o JSON.
     *
     * @return bool
     * @throws CliInterfaceException
     */
    public static function updateJSON(string $project_src, string $composer_packages_json) {
        if (!is_dir($project_src)) {
            throw new CliInterfaceException("Diretório \"$project_src\" não existe.");
        }

        if (!is_dir(dirname($composer_packages_json))) {
            throw new CliInterfaceException("Diretório para o \"$composer_packages_json\" não existe.");
        }

        $packages = json_encode(['packages' => self::findRepos($project_src)], JSON_PRETTY_PRINT);
        $packages = str_replace("\\/", "/", $packages);
        $packages = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $packages);

        file_put_contents($composer_packages_json, $packages);

        return true;
    }

    public static function isSymbolicLink($target) {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            if (file_exists($target) && readlink($target) != $target) {
                return true;
            }
        } else if (is_link($target)) {
            return true;
        }

        return false;
    }

}
