<?php
// Auto Load para dependencias do composer
require_once "../../vendor/autoload.php";

try {
    // Aplicação
    Blazar::init();
} catch (\Blazar\Core\BlazarException $e) {
    echo "<pre>";
    print_r($e);
}