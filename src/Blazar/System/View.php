<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\System;

use Blazar\Application;
use BrightNucleus\MimeTypes\MimeTypes;
use Mustache_Engine;

/**
 * Controle de saida de dados para exibição
 */
class View {

    // Retornos para preparação da página
    const PAGE_VIEW = 1;
    const PAGE_RESOURCE = 2;

    // Se o render já foi executado nesta instancia
    private $render_run = false;

    // Tipo do dado de saida (text/html, text/json, text/plain...)
    private $data_type = null;
    // Codificação da página
    private $codification = "utf-8";
    // Variavel setadas para a saida
    private $data = [];
    // Utilizar Mustache para renderizar
    private $mustache = false;
    // Caminho para o arquivo que será passado para a view
    private $file_output = "";
    // Forçar o download do path
    private $force_download = false;
    // Remover o arquivo depois de forçar o download
    private $remove_file = false;

    /**
     * @param string $codification
     *
     * @return View
     */
    public function setCodification(string $codification) {
        $this->codification = $codification;
        return $this;
    }

    /**
     * Utilizar mustache
     *
     * @param bool $mustache
     *
     * @return View
     */
    public function setMustache(bool $mustache) {
        $this->mustache = $mustache;
        return $this;
    }

    /**
     * Diz para a view que ela deve forçar um download no path informado
     *
     * @param bool $removeFile Excluir o arquivo depois do download
     *
     * @return $this
     */
    public function forceDownload(bool $removeFile = false) {
        $this->force_download = true;
        $this->remove_file = $removeFile;

        return $this;
    }

    /**
     * Substitui todos os valores do atributo $data
     *
     * @param mixed $data
     *
     * @return View
     */
    public function replaceAllData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Seta variaveis
     *
     * @param string $name O nome da variavel
     * @param mixed $value
     *
     * @return bool|View Retorna false se o nome for reservado
     */
    public function set(string $name, $value) {
        if (
            $name == "header" ||
            $name == "includes" ||
            $name == "text"
        ) {
            return false;
        }

        $this->data[$name] = $value;
        return $this;
    }

    /**
     * Mescla um array com os dados já setados
     *
     * @param array $dados <p>
     * Os indices já setados serão substituidos<br>
     * Caso o tipo da variavel data tenha sido setado com um valor que não seja um array, o valor antigo sera
     *     substituido pela nova variavel.
     * </p>
     *
     * @return bool|View Retorna false se o nome for reservado
     */
    public function mergeData(array $dados) {
        if (
            isset($dados['header']) ||
            isset($dados['includes']) ||
            isset($dados['text'])
        ) {
            return false;
        }

        if (is_array($this->data)) $this->data = array_merge($this->data, $dados);
        else $this->data = $dados;

        return $this;
    }

    /**
     * Faz um push em uma variavel do tipo array
     *
     * Se a variavel não existir, uma nova do tipo array é setada.
     * Caso a variavel já exista e não seja do tipo array, uma ViewException é gerada.
     *
     * @param string $name Nome da variavel
     * @param mixed $value
     *
     * @return View
     * @throws ViewException
     */
    public function pushArray(string $name, $value) {
        if (
            $name == "header" ||
            $name == "includes" ||
            $name == "text"
        ) {
            throw new ViewException("Variáveis reservadas.");
        }

        if (!isset($this->data[$name]))
            $this->data[$name] = [$value];
        else if (is_array($this->data[$name]))
            $this->data[$name][] = $value;
        else
            throw new ViewException("Variável não é um array.");

        return $this;
    }

    /**
     * Pega variavel
     *
     * @param string $name O nome da variavel desejada
     *
     * @return array|bool
     */
    public function get(string $name) {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            return false;
        }
    }

    /**
     * Metodo de auxilio para gerar páginas
     *
     * Prepara a saida de dados da página verificando se deve exibir um recurso ou a view
     * É necessario chamar o metodo render para exibir os dados
     *
     * @param string $view_path O caminho para a view da página
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
     *
     * @return string Retorna o tipo do carregamento que sera realizado (view ou resource)
     * @throws ViewException
     */
    protected function preparePage(string $view_path, array $page_res = [], $view_callback = null, $resource_callback = "render") {
        $param0 = Application::param(0, Application::PARAMS_APP);

        if ($param0 !== null && isset($page_res[$param0])) {
            $ext = pathinfo($page_res[$param0], PATHINFO_EXTENSION);

            $mimes = new MimeTypes();
            $mime = $mimes->getTypesForExtension($ext)[0];

            $this->setDataType($mime);
            $this->setFileOutput($page_res[$param0]);

            // Verifica se algum callback foi passado para executar
            if ($resource_callback !== null) {
                if (gettype($resource_callback) === "string" && method_exists($this, $resource_callback)) $this->$resource_callback();
                else if (is_callable($resource_callback)) call_user_func($resource_callback);
                else throw new ViewException("Metodo \"$resource_callback\" não existe na classe \"" . get_class($this) . "\".");

                if ($resource_callback !== "render") $this->render();
            }

            return self::PAGE_RESOURCE;
        } else {
            $this->setDataType("text/html");
            $this->setFileOutput($view_path);

            // Verifica se algum callback foi passado para executar
            if ($view_callback !== null) {
                if (gettype($view_callback) === "string" && method_exists($this, $view_callback)) $this->$view_callback();
                else if (is_callable($view_callback)) call_user_func($view_callback);
                else throw new ViewException("Metodo \"$view_callback\" não existe na classe \"" . get_class($this) . "\".");

                if ($view_callback !== "render") $this->render();
            }

            return self::PAGE_VIEW;
        }
    }

    /**
     * @param string $data_type
     *
     * @return View
     */
    public function setDataType(?string $data_type) {
        $this->data_type = $data_type;
        return $this;
    }

    /**
     * Define o arquivo que sera exibido na view
     *
     * @param string $file_output Caminho para o arquivo de saida de dados
     *
     * @return View
     */
    public function setFileOutput(string $file_output) {
        $this->file_output = $file_output;
        return $this;
    }

    /**
     * Exibir os dados preparados
     *
     * @return bool Retorna false caso o render já tenha sido executado na instancia
     */
    public function render() {
        if ($this->render_run) return false;
        $this->render_run = true;

        $nf_me = new Mustache_Engine();

        try {
            if (headers_sent($filename, $linenum)) {
                throw new ViewException("Os cabeçalhos já foram enviados em $filename na linha $linenum.");
            }

            $saida = ob_get_clean();
            if ($saida != "") {
                throw new ViewException("Alguns dados foram enviados antes da view ser gerada.\r\n\r\n$saida");
            }

            // Força o download do path informado
            if ($this->force_download) {
                if (is_string($this->file_output) && file_exists($this->file_output)) {
                    $ext = pathinfo($this->file_output, PATHINFO_EXTENSION);

                    $mimes = new MimeTypes();
                    $mime = $mimes->getTypesForExtension($ext)[0];

                    $file_path = explode("/", $this->file_output);
                    $file_path = end($file_path);

                    header('Content-type: ' . $mime);
                    header('Content-disposition: attachment; filename="' . $file_path . '"');
                    header("Content-Length: " . filesize($this->file_output));
                    readfile($this->file_output);

                    if ($this->remove_file) {
                        unlink($this->file_output);
                    }

                    exit();
                } else {
                    exit("Arquivo não encontrado.");
                }
            } // Saida de dados em um arquivo com HTML
            else if ($this->data_type == "text/html") {
                header('Content-Type: ' . $this->data_type . '; charset=' . $this->codification);

                if (!$this->mustache) extract($this->data);

                // Adiciona arquivo
                if (file_exists($this->file_output)) {
                    ob_start();
                    /** @noinspection PhpIncludeInspection */
                    require_once $this->file_output;
                    $content = ob_get_clean();

                    if ($this->mustache) echo $nf_me->render($content, $this->data);
                    else echo $content;
                } else {
                    throw new ViewException("Path incorreto.");
                }
            } // Saida de dados do tipo array e texto sem passar por um arquivo
            else if ($this->file_output == null || $this->file_output == "") {
                // Define o tipo de dados como JSON
                if ($this->data_type == null && is_array($this->data)) {
                    $dados = json_encode($this->data);
                    $data_type = "text/json";
                } // Define como texto
                else if ($this->data_type == null && is_string($this->data)) {
                    $dados = $this->data;
                    $data_type = "text/plain";
                } // Retorna o JSON com o tipo de dado informado
                else if (is_array($this->data)) {
                    $dados = json_encode($this->data);
                    $data_type = $this->data_type;
                } // Retorna o texto com o tipo de dado informado
                else {
                    $dados = $this->data;
                    $data_type = $this->data_type;
                }

                header('Content-Type: ' . $data_type . '; charset=' . $this->codification);

                echo $dados;
            } // Saida de dados em qualquer tipo de arquivo de texto
            else {
                $content_type = ($this->data_type != null) ? 'Content-Type: ' . $this->data_type . '; ' : "";
                header($content_type . 'charset=' . $this->codification);

                if (!$this->mustache) extract($this->data);

                // Adiciona arquivo
                if (file_exists($this->file_output)) {
                    ob_start();
                    /** @noinspection PhpIncludeInspection */
                    //$content = file_get_contents($this->file_output);
                    require $this->file_output;
                    $content = ob_get_clean();

                    if ($this->mustache) echo $nf_me->render($content, $this->data);
                    else echo $content;
                } else {
                    throw new ViewException("Path incorreto.");
                }
            }
        } catch (ViewException $e) {
            Log::e("View", $e, "view");
            echo "<br><br>Ocorreu um erro.<br>Favor entrar em contato com o Administrador.";
        }

        return true;
    }
}