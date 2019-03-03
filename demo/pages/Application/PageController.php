<?php

namespace Application;

use Blazar\Core\App;

class PageController {

    public static $info = [];

    public function __construct() {
        self::$info = [
            "site" => "Site exemplo",
            "hora" => date("H:i")
        ];

        $Page = App::next('class');
        new $Page();
    }

}