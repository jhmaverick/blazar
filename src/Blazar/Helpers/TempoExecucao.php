<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Helpers;

use Blazar\System\Log;

/**
 * Classe para media tempo de execução em uma parte do script
 */
class TempoExecucao {
    /**
     * A quantidade de Medições que foram declaradas
     * @var int
     */
    private static $quantidade_blocos = 0;

    /**
     * O numero da contagem de tempo atual
     * @var int
     */
    private $bloco_atual;
    /**
     * O tempo que foi iniciado a contagem
     * @var float
     */
    private $inicial;
    /**
     * O tempo em que a contagem foi parada
     * @var float
     */
    private $final;
    /**
     * O tempo entre o inicial e o final
     * @var float
     */
    private $tempo;

    /**
     * TempoExecucao constructor.
     *
     * Instancia a classe ja adicionando um numero para o bloco de execução e salvando o time inicial
     */
    public function __construct() {
        $this->inicial = microtime(true);

        self::$quantidade_blocos++;
        $this->bloco_atual = self::$quantidade_blocos;
    }

    /**
     * Encerra um ciclo de tempo de execução
     *
     * Se esse metodo for executado mais de uma vez, o ultimo tempo fechado será perdido
     *
     * @param bool $show_log Se true aciona o metodo de Exibir Logs
     */
    public function fecharTempo(bool $show_log = false) {
        $this->final = microtime(true);
        $this->tempo = $this->final - $this->inicial;

        if ($show_log) $this->showLog();
    }

    /**
     * Exibir o tempo que o bloco atual levou para ser executado
     */
    public function showLog() {
        if ($this->tempo > 0) Log::i("Tempo de execução do bloco de script N° " . $this->bloco_atual . " foi: " . $this->tempo);
        else Log::i("Tempo de Execução ainda não foi fechado.");
    }

    /**
     * @return int
     */
    public static function getQuantidadeblocos(): int {
        return self::$quantidade_blocos;
    }

    /**
     * @return int
     */
    public function getBlocoAtual(): int {
        return $this->bloco_atual;
    }

    /**
     * @return float
     */
    public function getInicial(): float {
        return $this->inicial;
    }

    /**
     * @return float
     */
    public function getFinal(): float {
        return $this->final;
    }

    /**
     * @return float
     */
    public function getTempo(): float {
        return $this->tempo;
    }
}