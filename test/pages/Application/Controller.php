<?php

namespace Application;

use Blazar\Application;

class Controller {

    public static $info = [];

    public function __construct() {
        self::$info = [
            "site" => "Site exemplo",
            "hora" => date("H:i")
        ];

        $modulo_inicial = Application::getNextParameter()['class'];
        new $modulo_inicial();
    }

}