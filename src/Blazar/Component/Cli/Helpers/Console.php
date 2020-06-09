<?php


namespace Blazar\Component\Cli\Helpers;


class Console {

    /**
     * Entrada de dados pelo console
     *
     * @param string|null $msg Mensagem para a linha
     *
     * @return string
     */
    public static function stdin(string $msg = null) {
        if ($msg != null) {
            $msg = trim($msg);
            echo "$msg ";
        }

        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));

        fclose($handle);

        return $line;
    }

    /**
     * Exibe uma mensagem de confirmação no terminal
     *
     * @param string $msg
     *
     * @return bool
     */
    public static function confirm(string $msg) {
        $resposta = Console::stdin("$msg (y/n):");
        $resposta = strtolower($resposta);

        return in_array($resposta, ['y', 'yes', 's', 'sim']);
    }

    /**
     * Exibe uma mensagem de confirmação no terminal
     *
     * @param array $options Opções para selecionar
     * @param string $msg Mensagem para a seleção
     * @param bool $confirmar Pedir confirmação da resposta
     *
     * @return bool
     */
    public static function select(array $options, string $msg = null, bool $confirmar = true) {
        if (empty($options)) return null;

        $options = array_map(function ($option) {
            return is_string($option) ? strtolower($option) : $option;
        }, $options);

        $msg = (!empty($msg)) ? $msg : 'Selecione uma das opções';

        if (count($options) <= 5) {
            $lista_opcoes = " (" . implode("|", $options) . "):";
        } else {
            $lista_opcoes = "\n* " . implode("\n* ", $options) . "\n\nInforme a opção: ";
        }

        $resposta = Console::stdin($msg . $lista_opcoes);
        $resposta = strtolower($resposta);

        if (!in_array($resposta, $options)) {
            self::println("Opção inválida!");
            $resposta = self::select($options, $msg, $confirmar);
        }

        if ($confirmar && !self::confirm("A opção escolhida foi \"$resposta\", deseja continuar?")) {
            $resposta = self::select($options, $msg, $confirmar);
        }

        return $resposta;
    }

    /**
     * Exibe o texto no terminal e salta uma linha
     *
     * @param string|array $msg
     * @param int $line_break Quantidade de quebra de linha
     */
    public static function println($msg, int $line_break = 1) {
        $list = is_array($msg) ? $msg : [$msg];

        foreach ($list as $v) {
            if (is_string($v)) {
                echo $v;
                self::newLine($line_break);
            }
        }
    }

    /**
     * Adiciona quebras de linha
     *
     * @param int $line_break Quantidade de quebra de linha
     */
    public static function newLine(int $line_break = 1) {
        for ($i = 0; $i < $line_break; $i++) echo "\n";
    }

}
