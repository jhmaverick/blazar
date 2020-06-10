<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace BlazarDev\RESTful;

use Blazar\Component\WebService\WebService;
use Blazar\Core\App;

class RESTful extends WebService {

    public function put(array $dados) {
        $url_params = App::param(null, App::PARAMS_APP);
        $condicao = $url_params[0] ?? null;
        $valor = $url_params[1] ?? null;
        $dados = json_encode($dados);

        return $condicao . "|" . $valor . "|" . $dados;
    }

    public function patch(array $dados) {
        $url_params = App::param(null, App::PARAMS_APP);
        $condicao = $url_params[0] ?? null;
        $valor = $url_params[1] ?? null;
        $dados = json_encode($dados);

        return $condicao . "|" . $valor . "|" . $dados;
    }

}