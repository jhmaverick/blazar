<?php
if (file_exists("../env.php")) require_once "../env.php";

// Auto Load para dependencias do composer
require_once "../../vendor/autoload.php";

// Aplicação
Blazar::init();