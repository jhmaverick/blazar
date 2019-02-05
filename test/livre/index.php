<?php
// Utilização sem manifesto e mapa de classes

// Auto Load para dependencias do composer
require_once "../../vendor/autoload.php";

use Blazar\Helpers\StrRes;

// Constantes e metodos do Framework
echo "Constante ROOT: " . ROOT . "<br>";
echo "Constante BASE: " . BASE . "<br>";
echo "Metodo StrRes::startsWith: " . (StrRes::startsWith("abcd", "ab") ? 1 : 0);