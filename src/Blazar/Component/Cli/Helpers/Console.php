<?php


namespace Blazar\Component\Cli\Helpers;


use Blazar\Component\TypeRes\StrRes;

/**
 * Class Console
 *
 * Método para controle de entrada e saída de dados no console.
 *
 * A classe utiliza fwrite ao invés de echo porque ele não lança headers na saída dos dados.
 * Isso evita problema com métodos que não podem ser executados após o header ser lançado como o setcookie por exemplo.
 *
 * @package NucleoGov\Cli\Helpers
 */
class Console {

    private static $interactive = true;

    private const TEXT_STYLES = [
        // Formatação
        'bold' => '1',
        'italic' => '3',
        'strikethrough' => '9',
        'underline' => '4',
        'double_underline' => '21',
        'curly_underline' => '4:3',
        'overline' => '53', // Underline em cima
        'reverse' => '7', // Inverte a cor da fonte e o background
        'invisible' => '8', // Texto invisível mas pode ser copiado
        'blink' => '5', // Pisca o texto
        // Cor da fonte
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1;31',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37',
        // Cor do Background
        'bg_black' => '40',
        'bg_red' => '41',
        'bg_green' => '42',
        'bg_yellow' => '43',
        'bg_blue' => '44',
        'bg_magenta' => '45',
        'bg_cyan' => '46',
        'bg_light_gray' => '47',
    ];

    /**
     * Quantidade de linhas exibidas na ultima execução do método replace
     *
     * @var int
     */
    private static $replace_lines_total = 0;

    /**
     * Força que a execução seja tratada como não interativa pelos métodos da classe
     *
     * @param bool $status Se a execução será não interativa.
     */
    public static function forceNoInteractive(bool $status) {
        // Inverte o valor informado
        self::$interactive = ($status === false);
    }

    /**
     * Retorna se o console é interativo
     *
     * @return bool
     */
    public static function isInteractive() {
        return (self::$interactive && posix_isatty(STDOUT));
    }

    /**
     * Entrada de dados pelo console
     *
     * @param string|null $msg Mensagem para a linha
     *
     * @return string
     */
    public static function stdin(string $msg = null) {
        // Não permite leitura em execuções não interativas
        if (!self::isInteractive()) {
            Console::errorln("STDIN não pode ser usado em execuções não interativas.");
            return false;
        }

        self::clearReplaceTotal();

        if ($msg != null) {
            $msg = trim($msg);
            fwrite(STDOUT, "$msg ");
        }

        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));

        fclose($handle);

        return $line;
    }

    /**
     * Exibe uma mensagem de confirmação no terminal.
     *
     * Se for chamado em uma execução não interativa irá retornar true.
     *
     * @param string $msg
     *
     * @return bool
     */
    public static function confirm(string $msg) {
        self::clearReplaceTotal();

        // Sempre retorna true quando está rodando de forma não interativa
        if (!self::isInteractive()) {
            return true;
        }

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

        self::clearReplaceTotal();

        // Não permite leitura em execuções não interativas
        if (!self::isInteractive()) {
            Console::errorln("STDIN não pode ser usado em execuções não interativas.");
            return false;
        }

        $options = array_map(function ($option) {
            return is_string($option) ? strtolower($option) : $option;
        }, $options);

        $msg = (!empty($msg)) ? $msg : 'Selecione uma das opções';
        $msg = self::getFormattedText($msg, 'bold');

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
     * Exibe o texto no terminal e salta uma linha.
     *
     * @param string|array $msg
     * @param int $line_break Quantidade de quebra de linha. A contagem inicia na linha atual. 0 não quebra linha.
     * @param string|string[] $styles Estilos para o texto.
     */
    public static function println($msg, ?int $line_break = 1, $styles = null) {
        self::clearReplaceTotal();

        $line_break = $line_break ?? 1;
        $styles = !is_array($styles) ? [$styles] : $styles;
        $styles = array_filter($styles, function ($item) {
            return is_string($item);
        });

        // Textos não tratados
        $itens = is_array($msg) ? $msg : [$msg];

        // Largura minima das linhas com background
        $pad_total = 50;
        // Margem lateral das linhas com background
        $margem_char = 4;

        $list = [];
        foreach ($itens as $text) {
            if (!is_string($text)) {
                continue;
            }

            // Separa as linhas
            $text_lines = explode("\n", $text);

            foreach ($text_lines as $line) {
                // Pega o tamanho da maior linha
                if (($str_length = strlen($line) + ($margem_char * 2)) > $pad_total) {
                    $pad_total = $str_length;
                }

                // Adiciona para a lista final
                $list[] = $line;
            }
        }

        $background_color = array_filter($styles, function ($style) {
            return StrRes::startsWith($style, 'bg_');
        });

        // Margem superior para textos com background
        if (!empty($background_color)) {
            $margem = str_repeat(" ", $pad_total);
            $margem = self::getFormattedText($margem, $styles);
            fwrite(STDOUT, $margem . "\n");
        }

        foreach ($list as $i => $text) {
            // Adiciona uma quebra de linha entre os textos
            if ($i > 0) {
                fwrite(STDOUT, "\n");
            }

            // Ações exclusivas para textos com background
            if (!empty($background_color)) {
                $text = str_repeat(" ", $margem_char) . $text . str_repeat(" ", $margem_char);
                $text = str_pad($text, $pad_total, " ", STR_PAD_RIGHT);
                $text = self::getFormattedText($text, $styles);
            } else if (!empty($styles)) {
                // Ações para textos com apenas cor de fonte
                $text = self::getFormattedText($text, $styles);
            }

            fwrite(STDOUT, $text);
        }

        // Margem inferior para textos com background
        if (!empty($background_color)) {
            $margem = str_repeat(" ", $pad_total);
            $margem = self::getFormattedText($margem, $styles);
            fwrite(STDOUT, "\n$margem");
        }

        // Aplica as quebras de linha após a mensagem
        self::newLine($line_break);
    }

    /**
     * Exibe a mensagem de erro no terminal e salta uma linha.
     *
     * A mensagem será exibida com uma fonte vermelha.
     *
     * @param string|array $msg
     * @param int $line_break Quantidade de quebra de linha. A contagem inicia na linha atual. 0 não quebra linha.
     */
    public static function errorln($msg, ?int $line_break = 1) {
        self::clearReplaceTotal();

        $line_break = $line_break ?? 1;

        $list = is_array($msg) ? $msg : [$msg];
        $list = array_filter($list, function ($item) {
            return is_string($item);
        });
        $list = array_values($list);

        foreach ($list as $i => $text) {
            // Adiciona uma quebra de linha entre os textos
            if ($i > 0) {
                fwrite(STDOUT, "\n");
            }

            if (is_string($text)) {
                $text = self::getFormattedText("$text", 'red');

                fwrite(STDERR, $text);
            }
        }

        // Aplica o restante de quebras de linha após a mensagem
        self::newLine($line_break);
    }

    /**
     * Adiciona quebras de linha
     *
     * @param int $line_break Quantidade de quebra de linha
     */
    public static function newLine(int $line_break = 1) {
        self::clearReplaceTotal();

        for ($i = 0; $i < $line_break; $i++) fwrite(STDOUT, "\n");
    }

    /**
     * Substitui linhas enviadas para o output.
     *
     * Após finalizar a sequencia de execução é importante limpar o total de linhas utilizadas pelo método para evitar bugs.
     * Para limpar o total execute o método "clearReplaceTotal".
     *
     * https://stackoverflow.com/questions/4320081/clear-php-cli-output
     *
     * @param string $message Mensagem da saída.
     * @param int|null $clear_lines <p>
     *  Indica a quantidade de linhas que serão substituídas.
     *  null: Substitui a quantidade de linhas exibidas na ultima execução deste método. Se o método ainda não tiver sido executado o padrão é 0.
     *  0: Adiciona a linha depois da ultima exibida e salva a quantidade adicionada para uma próxima adição. Útil para iniciar uma nova sequencia de exibição.
     *  Números maiores que 0 irão remover linhas anteriores independente de terem sido adicionadas ou não por este método.
     *  0 Será utilizado caso o valor informado seja menor que 0.
     * </p>
     */
    public static function replace(string $message = "", ?int $clear_lines = null) {
        if (!is_null($clear_lines)) {
            $clear_lines = ($clear_lines >= 0) ? $clear_lines : 0;
            self::$replace_lines_total = $clear_lines;
        }

        $term_width = exec('tput cols', $toss, $status);
        if ($status) {
            $term_width = 64; // Arbitrary fall-back term width.
        }

        $line_count = 0;
        foreach (explode("\n", $message) as $line) {
            $line_count += count(str_split($line, $term_width));
        }

        // Erasure MAGIC: Clear as many lines as the last output had.
        for ($i = 0; $i < self::$replace_lines_total; $i++) {
            // Return to the beginning of the line
            fwrite(STDOUT, "\r");
            // Erase to the end of the line
            fwrite(STDOUT, "\033[K");
            // Move cursor Up a line
            fwrite(STDOUT, "\033[1A");
            // Return to the beginning of the line
            fwrite(STDOUT, "\r");
            // Erase to the end of the line
            fwrite(STDOUT, "\033[K");
            // Return to the beginning of the line
            fwrite(STDOUT, "\r");
            // Can be consolodated into
            //fwrite(STDOUT, "\r\033[K\033[1A\r\033[K\r");
        }

        // Marca quantas linhas foram adicionadas para ser substituídas na próxima execução
        self::$replace_lines_total = $line_count;

        // Exibe a nova mensagem
        fwrite(STDOUT, "$message\n");
    }

    /**
     * Retorna o total de linhas adicionadas na ultima execução do "replace".
     *
     * @return int
     */
    public static function getReplaceTotal() {
        return self::$replace_lines_total;
    }

    /**
     * Limpa a marcação de linhas salvas pelo "replace".
     */
    public static function clearReplaceTotal() {
        self::$replace_lines_total = 0;
    }

    /**
     * Aplica formatação em um texto
     *
     * @param string $text Texto que receberá a cor.
     * @param string|string[] $styles Estilos para o texto.
     *
     * @return string
     */
    public static function getFormattedText(string $text, $styles = []) {
        $styles = !is_array($styles) ? [$styles] : $styles;

        $final_string = "";

        foreach ($styles as $style) {
            // Verifica se o estilo existe e se não existir considera que é um código de estilo e insere direto no texto
            if (is_string($style) && isset(self::TEXT_STYLES[$style])) {
                $final_string .= "\033[" . self::TEXT_STYLES[$style] . "m";
            } else if (is_string($style)) {
                // Remove o "m" do final para não duplicar
                $style = rtrim($style, "m");
                $final_string .= "\033[{$style}m";
            }
        }

        if (!empty($final_string)) {
            // Fecha os estilos definidos
            return $final_string . $text . "\033[0m";
        } else {
            // Retorna o texto original se nenhum estilo for válido
            return $text;
        }
    }

}
