<?php

use Blazar\System\API;

/**
 * API de gerenciamento de Login e Sessão
 *
 * Responsavel por criar, verificar e excluir logins
 */
class SingleAPI extends API {

    /**
     * Login no sistema.
     *
     * <b>$dados</b> array Dados da requisição.<br>
     * $dados[<b>email</b>] string E-Mail para o login.<br>
     * $dados[<b>senha</b>] string Senha para o login.
     *
     * @param array $dados
     * @return array|int
     */
    public function login(array $dados) {
        $result = (isset($dados['email']) && isset($dados['senha']));

        if ($result) return "dddd";
        else return "ssss";
    }
}