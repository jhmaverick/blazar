<?php

namespace Application\Pages;

use Blazar\Application;
use Blazar\System\Text;
use Blazar\System\View;
use Application\Controller;

class Home extends View {
    private $map_info;

    private $view_path = __DIR__ . "/home_view.php";
    private $page_res = [
        "home.css" => __DIR__ . "/home.css"
    ];

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = Application::getNextParameter(true);
        $type_load = $this->preparePage($this->view_path, $this->page_res);

        if ($type_load == "view") {
            $this->set("home_css", BASE . $this->map_info['url_path'] . "/home.css");
            $this->set("blazar", Text::get("blazar"));
            $this->set("msg", "Bem-vindo");
            $this->mergeData(Controller::$info);
        }

        $this->render();
    }
}