<?php

namespace Application\Pages;

use Application\PageController;
use Blazar\Application\View;
use Blazar\Application\ViewException;
use Blazar\System\ClassMap;
use Blazar\System\Log;

class Page2 extends View {
    private $map_info;

    private $view_path = __DIR__ . "/page2_view.mustache";

    /**
     * Home constructor.
     */
    public function __construct() {
        try {
            $this->map_info = ClassMap::current();

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