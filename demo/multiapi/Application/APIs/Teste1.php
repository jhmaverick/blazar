<?php

namespace Application\APIs;

class Teste1 extends Teste2 {

    public function acao1($dados) {
        return "aaa";
    }

    public function acao2($dados) {
        return $dados['buscar'] ?? 0;
    }

}