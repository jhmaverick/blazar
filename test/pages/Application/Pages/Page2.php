<?php

namespace Application\Pages;

use Application\PageController;
use Blazar\Component\View\View;
use Blazar\Component\View\ViewException;
use Blazar\Core\App;
use Blazar\Core\Log;

class Page2 extends View {
    private $map_info;

    private $view_path = __DIR__ . "/page2_view.mustache";

    /**
     * Home constructor.
     */
    public function __construct() {
        try {
            $this->map_info = App::current();

            $this->mustache(true);
            $this->preparePage($this->view_path, [], "showView")->render();
        } catch (ViewException $e) {
            Log::e($e);
        }
    }

    /**
     * Callback para exibir a view
     */
    protected function showView() {
        $this->merge(PageController::$info);
    }
}