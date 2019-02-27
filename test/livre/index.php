<?php
if (file_exists("../env.php")) require_once "../env.php";

// Utilização sem manifesto e mapa de classes

// Auto Load para dependencias do composer
require_once "../../vendor/autoload.php";

use Blazar\Component\TypeRes\StrRes;

// Constantes e metodos do Framework
echo "Constante ROOT: " . APP_ROOT . "<br>";
echo "Constante BASE: " . URL_BASE . "<br>";
echo "Metodo StrRes::startsWith: " . (StrRes::startsWith("abcd", "ab") ? 1 : 0);