<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace TestApp\Pages\DB;

use Blazar\Component\Dao\DaoException;
use Blazar\Component\View\View;
use Blazar\Component\View\ViewException;
use Blazar\Core\App;
use Blazar\Core\Log;
use TestApp\Model\Usuario;

class DB extends View {
    private $map_info;

    private $view_path = __DIR__ . '/db_view.mustache';

    /**
     * Home constructor.
     */
    public function __construct() {
        try {
            $this->map_info = App::current();
            $this->mustache(true);

            $this->preparePage($this->view_path);

            try {
                $u = new Usuario();
                $u->adicionar([
                    $u::COL_NOME => 'Teste ' . rand(1, 1000),
                    $u::COL_EMAIL => 'teste' . rand(1, 1000) . '@mail.com',
                    $u::COL_EMAIL => '62 9 9999-' . rand(1000, 9999),
                ]);

                $this->set('usuarios', $u->listar());
            } catch (DaoException $e) {
                Log::e($e);
            }

            $this->render();
        } catch (ViewException $e) {
            Log::e($e);
        }
    }
}