<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Application;

use Blazar\Component\WebService\WebService;

/**
 * Class API.
 */
class MultiAPI extends WebService {
    public function __construct() {
        $this->autoLogin();

        parent::__construct(true, 'acao', true);
    }

    /**
     * Auto Login.
     *
     * Verifica se existe um post ou get com o parametro para iniciar um sessão.<br>
     * <br>
     * Parametros necessarios:<br>
     * $_POST['auto_login']['login'] e $_POST['auto_login']['pass'] ou<br>
     * $_POST['auto_login']['login'] e $_POST['auto_login']['pass_md5']
     *
     * @return bool
     */
    private function autoLogin() {
        $dados = $this->getRequestData();

        // Faz login para acessos de apps externos
        if (isset($dados['auto_login']['login']) &&
            (isset($dados['auto_login']['pass']) || isset($dados['auto_login']['pass_md5']))
        ) {
            $login = $dados['auto_login']['login'];

            if (isset($dados['auto_login']['pass_md5'])) {
                $senha = $dados['auto_login']['pass_md5'];
                $is_md5 = true;
            } else {
                $senha = $dados['auto_login']['pass'];
                $is_md5 = false;
            }

            return true;
        }

        return false;
    }
}