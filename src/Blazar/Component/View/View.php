<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\View;

use Blazar\Component\Log\Log;
use Blazar\Core\App;
use BrightNucleus\MimeTypes\MimeTypes;
use Mustache_Engine;

/**
 * Controle de saida de dados para exibição.
 */
class View {

    /**#@+
     * Ações para preparação da página
     * @var int
     */
    const PAGE_RENDER_NONE = 1;
    const PAGE_RENDER_RESOURCE = 2;
    const PAGE_RENDER_VIEW = 3;
    const PAGE_RENDER_ALL = 4;
    /**#@-*/

    // Renderizar com mustache
    private static $mustache_default = false;

    // Se o render já foi executado nesta instancia
    private $render_run = false;

    // variáveis setadas para a saida
    private $data = [];
    // Caminho para o arquivo que será passado para a view
    private $template_file;
    // Tipo do dado de saida (text/html, text/json, text/plain...)
    private $content_type;
    // Codificação da página
    private $charset = 'utf-8';
    // Utilizar Mustache para renderizar
    private $mustache;

    /**
     * Metodo de auxilio para gerar páginas.
     *
     * Prepara a saida de dados da página verificando se deve exibir um recurso ou a view
     * É necessario chamar o metodo render para exibir os dados
     *
     * @param string|null $view_path O caminho para a view da página
     * @param array $page_res <p>
     * Recursos para a página<br>
     * Exemplo: <code>
     * ["estilo.css" => __DIR__ . "/estilo.css", "estilo2.css" => "res/estilo.css"]
     * </code>
     * </p>
     * @param string|object|null $view_callback <p>
     * Callback para ser executado após a preparação da view.<br>
     * O Metodo informado deve ser um membro da classe instanciada com visibilidade public ou protected
     * </p>
     * @param string|object|null $resource_callback <p>
     * Ação a ser tomada para os recursos da página.<br>
     * Um callback pode ser passado como string para executar após a preparação.<br>
     * O padrão é chamar a o metodo render.
     * </p>
     * @param int $render Chama o render e mata o processo
     *
     * @return View
     * @throws ViewException
     */
    protected function preparePage(?string $view_path, array $page_res = [], $view_callback = null, $resource_callback = null, int $render = self::PAGE_RENDER_RESOURCE) {
        $param0 = App::param(0, App::PARAMS_APP);

        if ($param0 !== null && isset($page_res[$param0])) {
            if ($resource_callback == 'render') {
                $resource_callback = null;
                $render = self::PAGE_RENDER_RESOURCE;
            }

            // Recursos da view
            $this->setTemplateFile($page_res[$param0]);

            // Verifica se algum callback foi passado para executar
            if ($resource_callback !== null) {
                if (gettype($resource_callback) === 'string' && method_exists($this, $resource_callback)) {
                    $this->$resource_callback();
                } elseif (is_callable($resource_callback)) {
                    call_user_func($resource_callback);
                } else {
                    throw new ViewException('Callback inválido.');
                }
            }

            if ($render == self::PAGE_RENDER_RESOURCE || $render == self::PAGE_RENDER_ALL) {
                $this->render();
                exit;
            }
        } elseif ($view_path != null) {
            if ($view_callback == 'render') {
                $view_callback = null;
                $render = self::PAGE_RENDER_VIEW;
            }

            // HTML da View
            $this->setContentType('text/html');
            $this->setTemplateFile($view_path);

            // Verifica se algum callback foi passado para executar
            if ($view_callback !== null) {
                if (gettype($view_callback) === 'string' && method_exists($this, $view_callback)) {
                    $this->$view_callback();
                } elseif (is_callable($view_callback)) {
                    call_user_func($view_callback);
                } else {
                    throw new ViewException('Callback inválido.');
                }
            }

            if ($render == self::PAGE_RENDER_VIEW || $render == self::PAGE_RENDER_ALL) {
                $this->render();
                exit;
            }
        } else {
            $this->setContentType('text/html');
            $this->reset('');
        }

        return $this;
    }

    /**
     * Exibir os dados preparados.
     *
     * Este método pode ser chamado apenas uma vez em cada instancia.
     *
     * @return bool Retorna false caso o render já tenha sido executado na instancia
     */
    public function render(): bool {
        // Evita que o render de uma mesma instancia seja chamado mais de uma vez
        if ($this->render_run) {
            return false;
        }
        $this->render_run = true;

        try {
            if ($this->template_file === null) {
                // Saida de dados do tipo array e texto sem passar por um arquivo
                $this->renderData();
            } else {
                // Saida de dados para qualquer tipo de arquivo de texto
                $this->renderFileContent();
            }
        } catch (ViewException $e) {
            Log::e($e, null, false, 'view');

            return false;
        }

        return true;
    }

    /**
     * Seta variáveis.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return View
     */
    public function set(string $name, $value) {
        if (!is_array($this->data)) {
            $this->data = ['root' => $this->data];
        }

        $this->data[$name] = $value;

        return $this;
    }

    /**
     * Pega variável.
     *
     * @param string|null $name O nome da variável desejada ou null para retornar todas
     *
     * @return mixed|array|null Retorna null se a variável não existir
     */
    public function get(?string $name) {
        if ($name === null) {
            return $this->data;
        } elseif (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            return null;
        }
    }

    /**
     * Substitui todos os valores do atributo $data.
     *
     * Se o valor adicionado não for um array o valor adicionado será movido para o indice "root" ao utilizar o
     * método "set" ou qualquer outra de inserção de dados.
     *
     * @param mixed $data
     *
     * @return View
     */
    public function reset($data) {
        $this->data = $data;

        return $this;
    }

    /**
     * Mescla um array com os dados já setados.
     *
     * @param array $dados
     *
     * @return View
     */
    public function merge(array $dados) {
        if (!is_array($this->data)) {
            $this->data = ['root' => $this->data];
        }

        $this->data = array_merge($this->data, $dados);

        return $this;
    }

    /**
     * Faz um push em uma variável do tipo array.
     *
     * Se a variável não existir, uma nova do tipo array é setada.<br>
     * Caso a variável já exista e não seja do tipo array ela será convertida e valor atual passará a ser o indice 0.
     *
     * @param string $name Nome da variável
     * @param mixed $value
     *
     * @return View
     */
    public function push(string $name, $value) {
        if (!is_array($this->data)) {
            $this->data = ['root' => $this->data];
        }

        if (!isset($this->data[$name])) {
            $this->data[$name] = [$value];
        } elseif (is_array($this->data[$name])) {
            $this->data[$name][] = $value;
        } else {
            $this->data[$name] = [$this->data[$name], $value];
        }

        return $this;
    }

    /**
     * @param string $charset
     *
     * Se mais de uma View for renderizada, apenas o charset da primeira será enviado
     *
     * @return View
     */
    public function setCharset(string $charset) {
        $this->charset = trim($charset);

        return $this;
    }

    /**
     * @param string $content_type
     *
     * Se mais de uma View for renderizada, apenas o content-type da primeira será enviado
     *
     * @return View
     */
    public function setContentType(?string $content_type) {
        $this->content_type = trim($content_type);

        return $this;
    }

    /**
     * Define o arquivo que sera exibido na view.
     *
     * @param string $template_file Caminho para o arquivo de saida de dados
     *
     * @return View
     */
    public function setTemplateFile(?string $template_file) {
        $this->template_file = $template_file;

        return $this;
    }

    /**
     * Renderizar como um templete do mustache.
     *
     * @param bool $mustache
     *
     * @return View
     */
    public function mustache(bool $mustache) {
        $this->mustache = $mustache;

        return $this;
    }

    /**
     * Torna o mustache a forma de renderização padrão.
     *
     * @param bool $mustache
     */
    public static function mustacheDefault(bool $mustache) {
        self::$mustache_default = $mustache;
    }

    /**
     * Renderização de arquivos.
     *
     * @throws ViewException
     */
    private function renderFileContent() {
        if (file_exists($this->template_file)) {
            $ext = pathinfo($this->template_file, PATHINFO_EXTENSION);

            $content_type = $this->content_type;

            if (!headers_sent()) {
                if ($content_type == null && in_array($ext, ['php', 'mustache', 'handlebars', 'hbs'])) {
                    // Força algumas extensões a sair com o tipo text/html
                    $content_type = 'text/html';
                } elseif ($content_type == null) {
                    // Se o tipo de saida não tiver sido definido tenta descobrir
                    $mimes = new MimeTypes();
                    $content_type = $mimes->getTypesForExtension($ext)[0];
                }

                $content_type = ($content_type != null) ? 'Content-Type: ' . $content_type . '; ' : '';
                header($content_type . 'charset=' . $this->charset);
            }

            // Evita erros caso a variavel tenha sido redefinida
            $data = is_array($this->data) ? $this->data : [];

            // Se nenhum variavel tiver sido passada e o template não retornar um html então exibe os dados direto
            if (count($data) == 0 && $ext != 'php' && $content_type != 'text/html') {
                header('Content-Length: ' . filesize($this->template_file));
                readfile($this->template_file);
            } else {
                if ($this->checkMustache()) {
                    // Carregar template com mustache
                    $content = file_get_contents($this->template_file);

                    $m = new Mustache_Engine();
                    echo $m->render($content, $data);
                } else {
                    // Desabilita os erros para não exibir problemas com variaveis indefinidas
                    global $log_ignore_errors;
                    $log_ignore_errors = true;

                    (function ($data) {
                        // Carregar de um arquivo PHP
                        extract($data);

                        /** @noinspection PhpIncludeInspection */
                        require $this->template_file;
                    })($data);

                    $log_ignore_errors = false;
                }
            }
        } else {
            throw new ViewException("Arquivo de saida \"{$this->template_file}\" não existe.");
        }
    }

    /**
     * Renderização para dados sem um arquivos.
     *
     * Exemplo um JSON ou um texto
     */
    private function renderData() {
        $content_type = $this->content_type;

        if (is_array($this->data) || is_object($this->data)) {
            // Define o tipo de dados como JSON
            $dados = json_encode($this->data);
            $content_type = $content_type ?? 'text/json';
        } else {
            // Define como texto
            $dados = $this->data;
            $content_type = $content_type ?? 'text/plain';
        }

        if (!headers_sent()) {
            $content_type = ($content_type != null) ? 'Content-Type: ' . $content_type . '; ' : '';
            header($content_type . 'charset=' . $this->charset);
        }

        echo $dados;
    }

    /**
     * Verifica se deve usar o mustache.
     * @return bool
     */
    private function checkMustache(): bool {
        if ($this->mustache !== null) {
            return $this->mustache;
        } else {
            return self::$mustache_default;
        }
    }
}