<?php

namespace Application;

use Blazar\Helpers\Request;
use Blazar\System\Api;

/**
 * Class API
 */
class ApiController extends Api {

    protected $action_name = "acao";

    public function __construct() {
        $this->autoLogin();
        Api::setAutostart(false);

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
        $dados = $this->requestType(false);

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