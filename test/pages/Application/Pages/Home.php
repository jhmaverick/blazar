<?php

namespace Application\Pages;

use Application\Controller;
use Blazar\Application;
use Blazar\System\Log;
use Blazar\System\Text;
use Blazar\System\View;
use Blazar\System\ViewException;

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
        try {
            $this->map_info = Application::getNextParameter(true);
            $this->preparePage($this->view_path, $this->page_res, "showView");
        } catch (ViewException $e) {
            Log::e($e);
        }
    }

    /**
     * Callback para exibir a view
     */
    protected function showView() {
        $this->set("home_css", $this->map_info['url_path'] . "/home.css");
        $this->set("blazar", Text::get("blazar"));
        $this->set("msg", "Bem-vindo");
        $this->mergeData(Controller::$info);
    }
}