<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace TestApp\Pages\Midias;

use Blazar\Component\View\View;
use Blazar\Core\App;

class Midias extends View {
    private $map_info;

    private $view_path = __DIR__ . '/midias_view.php';

    /**
     * Home constructor.
     */
    public function __construct() {
        $this->map_info = App::current();
        $this->preparePage($this->view_path);

        $this->render();
    }
}