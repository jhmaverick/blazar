<?php

namespace Pages;

use Blazar\ClassMap;
use Blazar\System\Log;
use Blazar\System\View;
use Model\Usuario;

class Home extends View {
    private $map_info;

    private $view_path = __DIR__ . "/home_view.mustache";

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = ClassMap::current();
        $type_load = $this->preparePage($this->view_path);
        $this->setMustache(true);

        if ($type_load == "view") {
            try {
                $u = new Usuario();
                $u->adicionar([
                    $u::COL_NOME => "Teste " . rand(1, 1000),
                    $u::COL_EMAIL => "teste" . rand(1, 1000) . "@mail.com",
                    $u::COL_EMAIL => "62 9 9999-" . rand(1000, 9999)
                ]);

                $this->set("usuarios", $u->listar());
            } catch (\Exception $e) {
                Log::e($e);
            }
        }

        $this->render();
    }
}