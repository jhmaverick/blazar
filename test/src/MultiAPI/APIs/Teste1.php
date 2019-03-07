<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace TestApp\MultiAPI\APIs;

class Teste1 extends Teste2 {
    public function acao1($dados) {
        return 'aaa';
    }

    public function acao2($dados) {
        return $dados['buscar'] ?? 0;
    }
}