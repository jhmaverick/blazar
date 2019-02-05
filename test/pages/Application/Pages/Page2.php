<?php

namespace Application\Pages;

use Blazar\Application;
use Blazar\System\View;
use Application\Controller;

class Page2 extends View {
    private $map_info;

    private $view_path = __DIR__ . "/page2_view.mustache";

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = Application::getNextParameter(true);
        $type_load = $this->preparePage($this->view_path);
        $this->setMustache(true);

        if ($type_load == "view") {
            $this->mergeData(Controller::$info);
        }

        $this->render();
    }
}