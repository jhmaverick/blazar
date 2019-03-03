<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Application;

use Blazar\Core\App;

class PageController {
    public static $info = [];

    public function __construct() {
        self::$info = [
            'site' => 'Site exemplo',
            'hora' => date('H:i'),
        ];

        $Page = App::next('class');
        new $Page();
    }
}