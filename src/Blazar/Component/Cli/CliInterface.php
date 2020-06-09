<?php

namespace Blazar\Component\Cli;

use Blazar\Component\Cli\Composer\Packages;
use Blazar\Component\Cli\Helpers\Args;
use Blazar\Component\Cli\Helpers\Console;
use Blazar\Component\TypeRes\JSONRes;

/**
 * Class CliInterface
 *
 * @package Blazar\Cli
 */
abstract class CliInterface {

    private static $start_file;

    /**
     * Inicia uma API da interface de comando
     *
     * @param array $argv Valores vindo diretamente da global argv
     */
    public static function init(array $argv) {
        try {
            $args = Args::get($argv);

            // Extrai o arquivo que iniciou o script da lista
            self::$start_file = array_shift($args);

            // Pega a interface que será executada
            self::extractAction($args, $interface);

            switch ($interface) {
                case 'composer':
                    $result = self::composer($args);
                    Console::println($result);

                    break;

                case 'json-read':
                    $result = self::readJson($args);
                    Console::println($result);

                    break;

                case 'json-save':
                    self::saveJson($args);

                    break;

                default:
                    Console::println("API não encontrada.", 2);

                    Console::println([
                        "Opções:",
                        "* ng-cli composer <acao> - Executa rotinas do composer.",
                        "* ng-cli json-read <file> [<index-route>] - Retorna os dados de um JSON. Se \"index-route\" for informando retorna apenas aquele índice.",
                        "* ng-cli json-save <file> <index-route> [<valor>] - Salva o valor no índice informado.",
                    ]);
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
        }
    }

    /**
     * Recursos para o composer
     *
     * @param array $args
     *
     * @return string
     * @throws CliInterfaceException
     */
    private static function composer(array $args) {
        // Separa a ação do restante dos argumentos
        self::extractAction($args, $acao);

        $acoes = ['packages'];

        if ($acao == 'packages') {
            Args::validateArgs($args, ['src'], ['empty' => false]);
            Args::validateArgs($args, ['output'], ['required' => false, 'empty' => false]);

            if (isset($args['output'])) {
                Packages::updateJSON($args['src'], $args['output']);
                return "Os dados das bibliotecas do composer foram atualizados em \"{$args['output']}\".";
            } else {
                $packages = json_encode(Packages::findRepos($args['src']), JSON_PRETTY_PRINT);
                $packages = str_replace("\\/", "/", $packages);
                return preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $packages);
            }
        } else {
            if (empty($acao)) {
                throw new CliInterfaceException("Informe uma ação para a API.\n - Lista de ações: " . implode("|", $acoes));
            } else {
                throw new CliInterfaceException("Ação \"$acao\" inválida para a API.\n - Lista de ações: " . implode("|", $acoes));
            }
        }
    }

    /**
     * Armazena dados em arquivos .json
     *
     * @param array $args
     *
     * @throws CliInterfaceException
     */
    private static function saveJson(array $args) {
        $filename = $args[0] ?? null;
        $index_route = $args[1] ?? null;
        $value = $args[2] ?? null;

        if ($filename == null) {
            throw new CliInterfaceException("O arquivo JSON não foi informado.");
        }

        if ($index_route == null) {
            throw new CliInterfaceException("A rota do JSON não foi informada.");
        }

        JSONRes::save($filename, $index_route, $value);
    }

    /**
     * Pega um dado em arquivos .json
     *
     * @param array $args
     *
     * @return string
     * @throws CliInterfaceException
     */
    private static function readJson(array $args) {
        // Pega um dado em arquivos .json
        $filename = $args[0] ?? null;
        $index_route = $args[1] ?? "/";

        if ($filename != null) {
            return JSONRes::read($filename, $index_route);
        } else {
            throw new CliInterfaceException("O arquivo JSON não foi informado.");
        }
    }

    private static function extractAction(array &$args, &$action) {
        $atual = current($args);

        if ($atual === false || !is_numeric(array_search($atual, $args))) {
            $action = null;
            return;
        }

        // Pega a interface que será executada
        $action = array_shift($args);

        // Reordena
        ksort($args, SORT_NATURAL);
    }

}