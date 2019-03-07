<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace TestApp\Pages\Home;

use Blazar\Component\Text\Text;
use Blazar\Component\View\View;
use Blazar\Component\View\ViewException;
use Blazar\Core\App;
use Blazar\Core\Log;

class Home extends View {
    private $map_info;

    //teste
    private $view_path = __DIR__ . '/home_view.php';
    private $page_res = [
        'home.css' => __DIR__ . '/home.css',
    ];

    /**
     * Home constructor.
     */
    public function __construct() {
        try {
            $this->map_info = App::current();

            $this->preparePage($this->view_path, $this->page_res, 'showView')->render();
        } catch (ViewException $e) {
            Log::e($e);
        }
    }

    /**
     * Callback para exibir a view.
     */
    protected function showView() {
        $this->set('home_css', $this->map_info['url_path'] . '/home.css');
        $this->set('blazar', Text::get('blazar'));
        $this->set('msg', 'Bem-vindo');
    }
}