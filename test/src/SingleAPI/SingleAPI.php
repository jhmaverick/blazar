<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace TestApp\SingleAPI;

use Blazar\Component\WebService\WebService;

/**
 * API de gerenciamento de Login e Sessão.
 *
 * Responsavel por criar, verificar e excluir logins
 */
class SingleAPI extends WebService {

    /**
     * Login no sistema.
     *
     * <b>$dados</b> array Dados da requisição.<br>
     * $dados[<b>email</b>] string E-Mail para o login.<br>
     * $dados[<b>senha</b>] string Senha para o login.
     *
     * @param array $dados
     *
     * @return array|int
     */
    public function login(array $dados) {
        $result = (isset($dados['email']) && isset($dados['senha']));

        if ($result) {
            return '1';
        } else {
            return '0';
        }
    }
}