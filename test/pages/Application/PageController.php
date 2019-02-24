<?php

namespace Application;

use Blazar\System\ClassMap;

class PageController {

    public static $info = [];

    public function __construct() {
        self::$info = [
            "site" => "Site exemplo",
            "hora" => date("H:i")
        ];

        $Page = ClassMap::next('class');
        new $Page();
    }

}