<?php

namespace Application\Pages;

use Application\PageController;
use Blazar\ClassMap;
use Blazar\System\Log;
use Blazar\System\View;
use Blazar\System\ViewException;

class Page2 extends View {
    private $map_info;

    private $view_path = __DIR__ . "/page2_view.mustache";

    /**
     * Home constructor.
     */
    public function __construct() {
        try {
            $this->map_info = ClassMap::current();

            $this->setMustache(true);
            $this->preparePage($this->view_path, [], "showView");
        } catch (ViewException $e) {
            Log::e($e);
        }
    }

    /**
     * Callback para exibir a view
     */
    protected function showView() {
        $this->mergeData(PageController::$info);
    }
}