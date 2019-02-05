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
use Blazar\Helpers\Request;
use Blazar\Helpers\StrRes;
use Exception;
use ReflectionMethod;

/**
 * Classe de criação de APIs para o manifest>map
 */
abstract class API {
    private static $autostart = true;

    private $class_api = true;
    private $started = false;
    private $api_map;
    private $view;
    // Retornos dos metodos de multiplas requisições
    private $retornos = [];

    /**
     * API constructor
     *
     * Esta classe deve ser chamada apenas em classes listadas no map do manifest.<br>
     * Para utilizar com multiplas apis, a classe controladora deve ser a ultima a ser chamada no map para que a API consiga
     * identificar os descendentes dela.
     *
     * @param bool|null $autostart <p>
     * Inicia a API com base na requisições já na instancia da classe.<br>
     * "null" pega o padrão do sistema.
     * </p>
     * @param bool $class_api <p>
     * Informa que a classe é uma API, se true apenas os metodos da classe herdeira serão validos.<br>
     * Se false irá trabalhar com as classes descendentes da classe atualmente utilizada informadas no map.
     * </p>
     */
    public function __construct(?bool $autostart = null, bool $class_api = true) {
        $this->class_api = $class_api;

        // Verifica se deve iniciar a api já na instancia a partir do parametro informado ou do padrão definido
        if ($autostart === true || ($autostart === null && self::$autostart)) $this->carregarRequisicoes();
    }

    /**
     * Altera a forma que as APIs irão se comportar durante a instancia
     *
     * Se true toda vez que uma classe que herde a API o auto start já sera executado.<br>
     * O padrão do sistema é true
     *
     * @param bool $status
     */
    public static function setAutostart(bool $status) {
        self::$autostart = $status;
    }

    /**
     * Inicia o processamento das APIs
     *
     * Define automaticamente qual é o tipo de requisição dependo dos parametros informados
     */
    public function carregarRequisicoes() {
        // Evitar que a API seja executada 2 vezes
        if ($this->started) return;
        $this->started = true;

        $this->api_map = Application::getNextParameter(true);
        $this->view = new View();

        $request = Request::get(true);
        $dados = $request["dados"];

        try {
            // Verifica qual tipo de requisição vai acontecer
            if (isset($dados['multi_request'])) {
                $this->multiRequest($dados);
            } else if (isset($dados['long_polling'])) {
                $this->longPolling($dados);
            } else if (isset($request['acao']) && is_string($request['acao'])) {
                $this->requestCommon($request['acao'], $dados);
            } else {
                $this->retornos = "Nenhuma ação foi passada para a API";
            }

            // Prepara o retorno
            if (isset($dados['multi_request'])) {
                $this->view->replaceAllData($this->retornos);
            } else if (isset($dados['long_polling'])) {
                $this->view->replaceAllData($this->retornos);
            } else {
                $this->view->replaceAllData($this->retornos);
            }
        } catch (Exception $e) {
            Log::e("Erro no gerenciador de APIs", $e);
            $this->view->replaceAllData("Não foi possível carregar a API");
        }

        $this->view->render();
    }

    /**
     * Metodo de requisição para multiplas APIs
     *
     * @param $dados
     *
     * @throws Exception
     */
    private function multiRequest($dados) {
        if (!isset($dados['params']) || !is_array(json_decode($dados['params'], true))) {
            return;
        }

        $dados['params'] = json_decode($dados['params'], true);

        foreach ($dados['params'] as $i => $v) {
            if (isset($v['acao']) && substr_count($v['acao'], "/") == 1) {
                // Separa a API da ação
                $d = explode("/", $v['acao']);
                unset($v['acao']);

                try {
                    if ($this->class_api) {
                        $class_path = get_class($this);
                    } else {
                        // Verifica se a API existe
                        $class_path = $this->api_map['sub'][$d[0]]['class'] ?? null;
                        if ($class_path === null || !class_exists($class_path)) {
                            throw new class("API não existe") extends Exception {
                                public $api_exception = true;
                            };
                        }
                    }

                    // Instancia a API
                    $ct = new $class_path(false, true);

                    // Transforma a ação no padrão do metodo
                    $metodo = $this->action2method($d[1]);

                    // Se o metodo da ação existir então ele é chamado
                    if (method_exists($ct, $metodo)) {
                        // Verifica se o metodo é publico
                        $reflection = new ReflectionMethod($ct, $metodo);
                        if ($reflection->isPublic()) $this->retornos[$i] = call_user_func_array([$ct, $metodo], [$v, $this->retornos]);
                        else $this->retornos[$i] = "Ação não encontrada na API";
                    } else {
                        $this->retornos[$i] = "Ação não encontrada na API";
                    }
                } catch (Exception $e) {
                    if (!isset($e->api_exception)) {
                        Log::e($e);
                        $this->retornos[$i] = "Não foi possível retornar os dados da API";
                    } else $this->retornos[$i] = "API não existe";
                }
            }
        }
    }

    /**
     * Metodo de requisição por long polling
     *
     * @param $dados
     *
     * @throws Exception
     */
    private function longPolling($dados) {
        if (!isset($dados['params']) || !is_array(json_decode($dados['params'], true))) {
            return;
        }

        // Tempo maximo para a execução
        $tempo_limite = 20;

        // Seta a execução do php com 10 segundo a mais do que o longpoll para não parar um script pela metade
        ini_set("max_execution_time", $tempo_limite + 10);

        $dados['params'] = json_decode($dados['params'], true);

        while (true) {
            $atualizacao = false;

            foreach ($dados['params'] as $i => $v) {
                if (isset($v['acao']) && substr_count($v['acao'], "/") == 1) {
                    // Separa a API da ação
                    $d = explode("/", $v['acao']);
                    unset($v['acao']);

                    try {
                        if ($this->class_api) {
                            $class_path = get_class($this);
                        } else {
                            // Verifica se a API existe
                            $class_path = $this->api_map['sub'][$d[0]]['class'] ?? null;
                            if ($class_path === null || !class_exists($class_path)) {
                                throw new class("API não existe") extends Exception {
                                    public $api_exception = true;
                                };
                            }
                        }

                        // Instancia a API
                        $ct = new $class_path(false, true);

                        // Transforma a ação no padrão do metodo
                        $metodo = $this->action2method($d[1]);

                        // Se o metodo da ação existir então ele é chamado
                        if (method_exists($ct, $metodo)) {
                            // Verifica se o metodo é publico
                            $reflection = new ReflectionMethod($ct, $metodo);
                            if ($reflection->isPublic()) {
                                $this->retornos[$i] = call_user_func_array([$ct, $metodo], [$v, $this->retornos]);

                                // TODO colocar um array na classe informando se o metodo aceita o long pool e que tipo retorno deve ser considerado como atualização

                                // Verifica se existe um dado para ser retornado
                                if (is_array($this->retornos[$i]) && count($this->retornos[$i]) > 0)
                                    $atualizacao = true;
                            } else $this->retornos[$i] = "Ação não encontrada na API";
                        } else {
                            $this->retornos[$i] = "Ação não encontrada na API";
                        }
                    } catch (Exception $e) {
                        if (!isset($e->api_exception)) {
                            Log::e($e);
                            $this->retornos[$i] = "Não foi possível retornar os dados da API";
                        } else $this->retornos[$i] = "API não existe";
                    }
                }
            }

            // Encerra a conexão caso tenha encontrado uma informação ou se o limite de tempo for atingido
            if ($atualizacao === true || time() >= ($tempo_limite + $_SERVER['REQUEST_TIME']) || connection_aborted()) {
                break;
            } else {
                // Reinicia o loop ate que o tempo limite seja atingido ou algo seja encontrado
                sleep(1);
                continue;
            }
        }
    }

    /**
     * Metodo de requisição comum
     *
     * @param string $acao
     * @param array $dados
     *
     * @throws Exception
     */
    private function requestCommon(string $acao, array $dados) {
        try {
            // Verifica se deve executar apenas os metodos da classe
            if ($this->class_api) {
                $ControllerClass = get_class($this);
            } else {
                // Pega o parametro da URL para obter a classe
                $ControllerClass = Application::getNextParameter()['class'];
                if (StrRes::endsWith($ControllerClass, "\\Error")) {
                    throw new class("API não existe") extends Exception {
                        public $api_exception = true;
                    };
                }
            }

            // Instancia a API
            $ct = new $ControllerClass(false, true);

            // Transforma a ação no padrão do metodo
            $metodo = $this->action2method($acao);

            // Se o metodo da ação existir então ele é chamado
            if (method_exists($ct, $metodo)) {
                // Verifica se o metodo é publico
                $reflection = new ReflectionMethod($ct, $metodo);
                if ($reflection->isPublic()) $this->retornos = call_user_func_array([$ct, $metodo], [$dados]);
                else $this->retornos = "Ação não encontrada";
            } else $this->retornos = "Ação não encontrada";
        } catch (Exception $e) {
            if (!isset($e->api_exception)) {
                Log::e($e);
                $this->retornos = "Não foi possível retornar os dados da API";
            } else $this->retornos = "API não existe";
        }
    }

    /**
     * Converte o nome de uma ação para o padrão dos metodos
     *
     * Exemplo: "cadastrar_usuario" -> "cadastrarUsuario"
     *
     * @param string $nome o nome da ação que será convertida para o nome do metodo
     *
     * @return mixed
     */
    private function action2method(string $nome) {
        $nome = ucwords(str_replace("_", " ", $nome));
        $nome = str_replace(" ", "", $nome);

        return lcfirst($nome);
    }
}