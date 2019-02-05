<?php

namespace Application;

use Blazar\System\API;
use Blazar\Helpers\Request;

/**
 * Class API
 */
class Controller extends API {

    public function __construct() {
        $this->autoLogin();
        API::setAutostart(false);

        parent::__construct(true, false);
    }

    /**
     * Auto Login
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
        $dados = Request::get(false);

        // Faz login para acessos de apps externos
        if (isset($dados["auto_login"]['login']) &&
            (isset($dados["auto_login"]['pass']) || isset($dados["auto_login"]['pass_md5']))
        ) {
            $login = $dados["auto_login"]['login'];

            if (isset($dados["auto_login"]['pass_md5'])) {
                $senha = $dados["auto_login"]['pass_md5'];
                $is_md5 = true;
            } else {
                $senha = $dados["auto_login"]['pass'];
                $is_md5 = false;
            }

            return true;
        }

        return false;
    }
}