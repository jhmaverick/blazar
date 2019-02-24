<?php

namespace InNetworking;

use Blazar\Application\View;
use Blazar\System\OutputImg;
use BrightNucleus\MimeTypes\MimeTypes;

class Midias {
    public function __construct() {
        $local_file = str_replace(BASE, "", URL);
        $local_file = explode("?", $local_file)[0];

        $mimes = new MimeTypes();

        $ext = pathinfo($local_file, PATHINFO_EXTENSION);
        $mime = $mimes->getTypesForExtension($ext)[0];

        if (isset($_GET["download"])) {
            $view = new View();

            $view->setTemplateFile($local_file)
                ->forceDownload()
                ->render();
        } else if ($ext == "jpg" || $ext == "png") {
            new OutputImg($local_file);
        } else {
            header("Content-type: $mime");
            readfile($local_file);
        }
    }
}