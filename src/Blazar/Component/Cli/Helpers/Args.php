<?php


namespace Blazar\Component\Cli\Helpers;


use Blazar\Component\Cli\CliInterfaceException;

class Args {

    /**
     * Trata os argumentos da linha de comando
     *
     * - Argumentos com nome:
     *      Iniciam com "--" e possui no mínimo 2 caracteres no nome, sendo o primeiro alfa numerico.
     *      O valor padrão do argumento é true, um valor diferente pode ser informado após um "=".
     *      Ex: "--nome1 --nome2=valor" retorna ['nome1' => true, 'nome2' => 'valor'].
     * - Argumentos booleanos:
     *      Iniciam com apenas um "-" e possuem apenas letras.
     *      Cada caractere informado será transformado em um indice com true no valor.
     *      Ex: -abc retorna ['a' => true, 'b' => true, 'c' => true].
     * - Argumentos restantes:
     *      Todos os argumentos que não se enquadrem nas argumentos acima.
     *
     * @param array $argv Lista de argumentos recebidos
     * @param array $default_args Valores padrões
     * @param array $params <p>
     *  Parâmetros para alterar o comportamento do método.
     *  - next_as_value: (bool) Se o nome do argumento for informado sem o "=", o valor será o proximo argumento.
     *  - return_args: (string) (all|named|rest)
     * </p>
     *
     * @return array Retorna todos os argumentos posicionando os argumentos não nomeados no inicio da lista
     */
    public static function get(array $argv, array $default_args = [], array $params = []) {
        $named_args = [];
        $rest_args = [];

        $params['next_as_value'] = (isset($params['next_as_value']) && $params['next_as_value'] == true);
        $params['return_args'] = (isset($params['return_args']) && is_string($params['return_args']) && in_array($params['return_args'], ['all', 'named', 'rest']))
            ? $params['return_args'] : 'all';

        // Percorre os argumentos extraindo os que foram informados como parâmetros
        foreach ($argv as $idx => $val) {
            if (!isset($argv[$idx])) {
                continue;
            }

            if (strlen($val) > 3
                && substr($val, 0, 2) == '--'
                && ctype_alnum(substr($val, 2, 1))
            ) {
                // Argumentos que recebem valor

                $parts = explode('=', $val);
                // Remove "--"
                $name = substr($parts[0], 2);

                if (count($parts) > 1) {
                    // Pega o restante depois do primeiro "="
                    $value = substr($val, (strlen($parts[0]) + 1));
                    $named_args[$name] = $value;
                } else if ($params['next_as_value'] && isset($argv[$idx + 1])) {
                    // Se não encontrar um "=" o valor será o proximo argumento
                    $named_args[$name] = $argv[$idx + 1];
                    unset($argv[$idx + 1]);
                } else {
                    // Se estiver na ultima posição e sem valor
                    $named_args[$name] = true;
                }
            } else if (substr($val, 0, 1) == '-') {
                // Argumentos booleanos
                $chars = str_split($val);
                foreach ($chars as $char) {
                    if (ctype_alpha($char)) {
                        $named_args[$char] = true;
                    }
                }
            } else {
                // Restante dos argumentos
                $rest_args[$idx] = $val;
            }
        }

        if ($params['return_args'] == 'named') {
            // Argumentos padrões e os nomeados
            $args = array_merge($default_args, $named_args);
        } else if ($params['return_args'] == 'rest') {
            // Argumentos padrões e os não nomeados
            $args = array_merge($default_args, $rest_args);
        } else {
            // Uni os argumentos colocando os não nomeados nas primeiras posições
            $args = array_merge($default_args, $rest_args, $named_args);
        }

        ksort($args, SORT_NATURAL);

        return $args;
    }

    /**
     * @param array $args
     * @param $arg_name
     * @param array $validacao
     *
     * @throws CliInterfaceException
     */
    public static function validateArgs(array $args, $arg_name, array $validacao = []) {
        $arg_name = is_string($arg_name) ? [$arg_name] : $arg_name;
        $validacao['types'] = $validacao['types'] ?? [];

        $validacao['types'] = is_string($validacao['types']) ? [$validacao['types']] : $validacao['types'];
        $validacao['empty'] = (isset($validacao['empty']) && is_bool($validacao['empty'])) ? $validacao['empty'] : true;
        $validacao['required'] = (isset($validacao['required']) && is_bool($validacao['required'])) ? $validacao['required'] : true;

        if (!is_array($validacao['types'])) {
            throw new CliInterfaceException("\"{$validacao['types']}\" deve ser um array ou string.");
        }

        if (!is_array($arg_name) || empty($arg_name)) {
            throw new CliInterfaceException("\"$arg_name\" deve ser um array ou string.");
        }

        $validacao['types'] = array_filter($validacao['types'], function ($item) {
            return is_string($item);
        });

        $arg_name = array_filter($arg_name, function ($item) {
            return is_string($item);
        });

        foreach ($arg_name as $item) {
            if (!is_string($item)) {
                throw new CliInterfaceException("O nome do argumento deve ser uma string.");
            }

            if ($validacao['required'] && !isset($args[$item])) {
                throw new CliInterfaceException("Argumento \"$item\" não encontrado.");
            }

            if (isset($args[$item]) && !$validacao['empty'] && empty($args[$item])) {
                throw new CliInterfaceException("Argumento \"$item\" não encontrado ser vazio.");
            }

            if (isset($args[$item])
                && !empty($validacao['types'])
                && (!$validacao['empty'] || $validacao['empty'] && !empty($args[$item]))
                && !in_array(gettype($args[$item]), $validacao['types'])
            ) {
                $msg = "O tipo do argumento \"$item\" não é válido.\n";
                $msg .= (count($validacao['types']) == 1) ? "Tipo permitido: " : "Tipos permitidos: ";
                $msg .= implode("|", $validacao['types']);

                throw new CliInterfaceException($msg);
            }
        }
    }

}
