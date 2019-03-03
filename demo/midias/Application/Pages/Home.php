<?php

namespace Application\Pages;

use Blazar\Component\View\View;
use Blazar\Core\App;

class Home extends View {
    private $map_info;

    private $view_path = __DIR__ . "/home_view.php";

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = App::current();
        $this->preparePage($this->view_path);

        $this->render();
    }
}