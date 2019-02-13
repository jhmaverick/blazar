<?php

namespace Application\Pages;

use Application\Controller;
use Blazar\ClassMap;
use Blazar\System\View;

class Home extends View {
    private $map_info;

    private $view_path = __DIR__ . "/home_view.php";

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = ClassMap::current();
        $this->preparePage($this->view_path);

        $this->render();
    }
}