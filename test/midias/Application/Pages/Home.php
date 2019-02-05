<?php

namespace Application\Pages;

use Blazar\Application;
use Blazar\System\View;
use Application\Controller;

class Home extends View {
    private $map_info;

    private $view_path = __DIR__ . "/home_view.php";

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = Application::getNextParameter(true);
        $this->preparePage($this->view_path);

        $this->render();
    }
}