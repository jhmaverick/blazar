<?php
// Utilização sem manifesto e mapa de classes
// Auto Load para dependências do composer
require_once "../../vendor/autoload.php";

Blazar::prepare();

use Blazar\Component\TypeRes\StrRes;
use Blazar\Component\FileSystem\FileSystem;

// Constantes e métodos do Framework
echo "<b>Path resolve:</b> " . FileSystem::pathResolve("path", "to/file.txt") . "<br>";
echo "<b>Constante ROOT:</b> " . SOURCE_DIR . "<br>";
echo "<b>Constante BASE:</b> " . URL_BASE . "<br>";
echo "<b>Método StrRes::startsWith:</b> " . (StrRes::startsWith("abcd", "ab") ? 1 : 0);