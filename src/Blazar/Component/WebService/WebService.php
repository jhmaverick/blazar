<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\WebService;

use Blazar\Component\Log\Log;
use Blazar\Component\TypeRes\StrRes;
use Blazar\Component\View\View;
use Blazar\Core\App;
use Blazar\Core\Manifest;
use Exception;
use ReflectionMethod;

/**
 * Gerenciamento de APIs.
 *
 * As APIs disponíveis devem estar em manifest>map<br>
 * <br>
 * Os métodos das APIs irão receber os seguintes parâmetros:<br>
 * * 0 array [data] As informações da requisição.<br>
 * * 1 array|null [result_list] (Apenas para multi_request e long_polling) A lista de resultados gerados pelas
 * requisições anteriores<br>
 * * 2 object|null [instance] (Apenas para webservice) A instância da classe webservice.<br>
 */
class WebService {

    private static $default_method_param = 'method';
    private static $started = false;

    private $method_name;
    private $is_controller;
    private $current_map;
    private $api_map;
    private $view;
    private $inherited_methods;
    private $restful;
    private $return_info;
    private $long_polling;

    // Retornos dos métodos de múltiplas requisições
    private $result_list = [];

    /**
     * WebService constructor.
     *
     * Define automaticamente qual é o tipo de requisição dependo dos parâmetros informados.<br>
     * <br>
     * Esta classe deve ser chamada apenas em classes listadas no map do manifest.<br>
     * Para utilizar com multiplas apis, a classe controladora deve ser a ultima a ser chamada no map para que a API
     * consiga identificar os descendentes dela.
     *
     * @param array $config <p>Altera o comportamento do WebService.<br>
     * As configurações também podem ser inseridas no mapa da API no Manifest.<br>
     * <br>
     * <b>Índices:</b><br>
     * * controller - (bool) Default false. Este argumento indica que a classe instanciada serve apenas para controlar as suas classes filhas definidas no índice "sub".<br>
     *      Se true, a classe filha será instanciada e o método desejado será executado.<br>
     *      Se false, o script irá procurar o método na própria classe.<br>
     * * method - (string) Default "method". Ação padrão para a chamada dos métodos das requisições(Não funciona com o padrão RESTful).<br>
     *      A classe API deve possuir um método com o nome enviado pelo parâmetro.<br>
     *      Ex: .../user/?method=show_name<br>
     *      <code>public function showName() {...</code><br>
     * * inherited - (bool) Default false. Habilitar uso de métodos herdados.<br>
     * * restful - (bool) Default false. Se deve usar o padrão REST.<br>
     * * return_info - (bool) Default false. Se true o retorno será um array com os campos: "data", "status" e "error" quando existir.<br>
     * * long_polling - (bool) Default false. Se true permite que a API seja executada com long polling.<br>
     * </p>
     */
    public function __construct(array $config = []) {
        // Evita que 2 Webservices sejam iniciados em uma mesma requisição
        if (self::$started) {
            return;
        }
        self::$started = true;

        $this->current_map = App::current();
        $this->api_map = Manifest::map($this->current_map['route']);

        // Mescla as configurações passadas na instância com as do mapa de classe.
        // As configurações passadas na instância terão prioridade
        $config = array_merge($this->api_map, $config);

        $this->method_name = $config['method'] ?? self::$default_method_param;
        $this->is_controller = ($config['controller'] ?? false) == true;
        $this->inherited_methods = ($config['inherited'] ?? false) == true;
        $this->restful = ($config['restful'] ?? false) == true;
        $this->return_info = ($config['return_info'] ?? false) == true;
        $this->long_polling = ($config['long_polling'] ?? false) == true;

        $this->view = new View();

        // Pega todos os dados recebidos por GET e POST
        $request_data = array_merge($_GET, $_POST);

        try {
            // Verifica qual tipo de requisição vai acontecer
            if ((!isset($this->api_map['restful']) && $this->restful == true)
                || (isset($this->api_map['restful']) && $this->api_map['restful'] == true)
            ) {
                $this->restfulRequest();
            } elseif (isset($request_data['multi_request'])) {
                $this->multiRequest($request_data);
            } elseif (isset($request_data['long_polling'])) {
                // Verifica se a API pode ser executada em long_polling
                if ($this->long_polling) {
                    $this->longPollingRequest($request_data);
                } else {
                    $this->applyResult('This API cannot be run in Long poll', 403);
                }
            } elseif (isset($request_data[$this->method_name]) && is_string($request_data[$this->method_name])) {
                $this->request($request_data);
            } else {
                $this->applyResult(null, 405);
            }
        } catch (Exception $e) {
            Log::e('Erro no gerenciador de APIs', $e);
            $this->applyResult(null, 500);
        }

        // Aplica os dados gerados pela API na View
        $this->view->reset($this->result_list);
        $this->view->render();
    }

    /**
     * Método de requisição simples.
     *
     * @param array $request_data
     *
     * @throws Exception
     */
    private function request(array $request_data) {
        try {
            // Pega o nome do método e remove ele da lista
            $method = $request_data[$this->method_name];
            unset($request_data[$this->method_name]);

            if ($this->is_controller === false) {
                $webservice = null;

                // Procura o método na própria classe
                $api_class = get_class($this);
                $api = $this;
            } else {
                $webservice = $this;

                // A classe é apenas um controlador e o método da API está em uma classe filha
                $api_class = App::next('class');
                if ($api_class == null || !class_exists($api_class)) {
                    $this->applyResult(null, 404);
                    return;
                }

                $api = new $api_class();
            }

            // Transforma a ação no padrão do método
            $metodo = $this->param2method($method);

            if ($this->methodValidate($api_class, $metodo)) {
                $result = call_user_func_array([$api, $metodo], [$request_data, null, $webservice]);
                $this->applyResult($result, 200);
            } else {
                $this->applyResult(null, 405);
            }
        } catch (Exception $e) {
            Log::e($e);
            $this->applyResult(null, 500);
        }
    }

    /**
     * Requisição no padrão RESTful.
     *
     * @throws Exception
     */
    private function restfulRequest() {
        try {
            // Pega o tipo da requisição para chamar um método com o mesmo nome dentro da classe
            $method = strtolower($_SERVER['REQUEST_METHOD']);
            $request_types = ['get', 'post', 'put', 'delete', 'head', 'patch', 'connect', 'options', 'trace'];

            if (!in_array($method, $request_types)) {
                $this->applyResult(null, 405);
                return;
            }

            switch ($method) {
                case 'delete':
                case 'patch':
                case 'put':
                    parse_str(file_get_contents("php://input"), $request_data);
                    break;

                case 'post':
                    $request_data = $_POST;
                    break;

                case 'get':
                    $request_data = $_GET;
                    break;

                default:
                    $request_data = $_REQUEST;
            }

            // Verifica se deve executar apenas os métodos da classe
            if ($this->is_controller === false) {
                $webservice = null;

                // Procura o método na própria classe
                $api_class = get_class($this);
                $api = $this;
            } else {
                $webservice = $this;

                // A classe é apenas um controlador e o método da API está em uma classe filha
                $api_class = App::next('class');
                if ($api_class == null || !class_exists($api_class)) {
                    $this->applyResult(null, 404);
                    return;
                }

                $api = new $api_class();
            }

            if ($this->methodValidate($api_class, $method)) {
                $result = call_user_func_array([$api, $method], [$request_data, null, $webservice]);
                $this->applyResult($result, 200);
            } else {
                $this->applyResult(null, 405);
            }
        } catch (Exception $e) {
            Log::e($e);
            $this->applyResult(null, 500);
        }
    }

    /**
     * Requisição para múltiplas APIs.
     *
     * @param $data
     *
     * @throws Exception
     */
    private function multiRequest($data) {
        if (!isset($data['params']) || !is_array(json_decode($data['params'], true))) {
            return;
        }

        $data['params'] = json_decode($data['params'], true);

        foreach ($data['params'] as $i => $v) {
            if (isset($v[$this->method_name]) && substr_count($v[$this->method_name], '/') == 1) {
                // Separa a API da ação
                list($map_name, $method_name) = explode('/', $v[$this->method_name]);
                unset($v[$this->method_name]);

                try {
                    if ($this->is_controller === false) {
                        $webservice = null;

                        // Procura o método na própria classe
                        $api_class = get_class($this);
                        $api = $this;
                    } else {
                        $webservice = $this;

                        // A classe é apenas um controlador e o método da API está em uma classe filha
                        $api_class = $this->api_map['sub'][$map_name]['class'] ?? null;
                        if ($api_class == null || !class_exists($api_class)) {
                            $this->applyResult(null, 404, $i);
                            continue;
                        }

                        $api = new $api_class();
                    }

                    // Transforma a ação no padrão do método
                    $method = $this->param2method($method_name);

                    if ($this->methodValidate($api_class, $method)) {
                        $result = call_user_func_array([$api, $method], [$v, $this->result_list, $webservice]);
                        $this->applyResult($result, 200, $i);
                    } else {
                        $this->applyResult(null, 405, $i);
                    }
                } catch (Exception $e) {
                    Log::e($e);
                    $this->applyResult(null, 500, $i);
                }
            }
        }
    }

    /**
     * Método de requisição por long polling.
     *
     * @param $data
     *
     * @throws Exception
     */
    private function longPollingRequest($data) {
        if (!isset($data['params']) || !is_array(json_decode($data['params'], true))) {
            return;
        }

        // Tempo maximo para a execução
        $tempo_limite = 20;

        // Seta a execução do php com 10 segundo a mais do que o longpoll para não parar um script pela metade
        ini_set('max_execution_time', $tempo_limite + 10);

        $data['params'] = json_decode($data['params'], true);

        while (true) {
            $atualizacao = false;

            foreach ($data['params'] as $i => $v) {
                if (isset($v[$this->method_name]) && substr_count($v[$this->method_name], '/') == 1) {
                    // Separa a API da ação
                    list($map_name, $method_name) = explode('/', $v[$this->method_name]);
                    unset($v[$this->method_name]);

                    try {
                        if ($this->is_controller === false) {
                            $webservice = null;

                            // Procura o método na própria classe
                            $api_class = get_class($this);
                            $api = $this;
                        } else {
                            $webservice = $this;

                            // A classe é apenas um controlador e o método da API está em uma classe filha
                            $api_class = $this->api_map['sub'][$map_name]['class'] ?? null;
                            if ($api_class == null || !class_exists($api_class)) {
                                $this->applyResult(null, 404, $i);

                                continue;
                            }

                            $api = new $api_class();
                        }

                        // Transforma a ação no padrão do método
                        $method = $this->param2method($method_name);

                        if ($this->methodValidate($api_class, $method)) {
                            $result = call_user_func_array([$api, $method], [$v, $this->result_list, $webservice]);
                            $this->applyResult($result, 200, $i);

                            // Verifica se existe um dado para ser retornado
                            if (is_array($result) && count($result) > 0) {
                                $atualizacao = true;
                            }
                        } else {
                            $this->applyResult(null, 405, $i);
                        }
                    } catch (Exception $e) {
                        Log::e($e);
                        $this->applyResult(null, 500, $i);
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
     * Verifica se é um método valido.
     *
     * Verifica se o método existe, se não começa com "__", se é público, se não é desta classe, e se pode ser herdado.
     *
     * @param string $class_name
     * @param string $method
     *
     * @return bool
     * @throws \ReflectionException
     */
    private function methodValidate(string $class_name, string $method): bool {
        if (!StrRes::startsWith($method, '__') && method_exists($class_name, $method)) {
            // Verifica se o método é publico
            $reflection = new ReflectionMethod($class_name, $method);

            if ($reflection->isPublic() &&
                $reflection->class !== __CLASS__ &&
                ($this->inherited_methods || $reflection->class === $class_name)
            ) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * Converte o nome de uma ação para o padrão dos métodos.
     *
     * Exemplo: "cadastrar_usuario" -> "cadastrarUsuario"
     *
     * @param string $nome o nome da ação que será convertida para o nome do método
     *
     * @return mixed
     */
    private function param2method(string $nome) {
        $nome = ucwords(str_replace('_', ' ', $nome));
        $nome = str_replace(' ', '', $nome);

        return lcfirst($nome);
    }

    /**
     * Monta a estrutura de resultados
     *
     * @param mixed $data
     * @param int $code
     * @param string $multi_request_id
     */
    private function applyResult($data = null, int $code = 200, $multi_request_id = null) {
        $error = null;

        // Verifica mensagem de erro
        // https://www.restapitutorial.com/httpstatuscodes.html
        switch ($code) {
            case 403:
                $error = 'Forbidden';

                break;

            case 404:
                $error = 'Not Found';

                break;

            case 405:
                $error = 'Method Not Allowed';

                break;

            case 500:
                $error = 'Internal Server Error';

                break;
        }

        if ($this->return_info) {
            // Retorno com informações
            $result_list = [
                'status' => $code,
                'data' => $data,
            ];

            if (!empty($error)) {
                $error = $error . (!empty($data) ? " - $data" : "");
                $result_list['error'] = $error;
                $result_list['data'] = null;
            }
        } else {
            // Aplica o resultado direto no retorno
            if (!empty($error)) {
                $result_list = $error . (!empty($data) ? " - $data" : "");
            } else {
                $result_list = $data;
            }
        }

        if (!empty($multi_request_id)) {
            // Insere os dados no índice do multi request
            $this->result_list[$multi_request_id] = $result_list;
        } else {
            // Retorno padrão das requisições
            http_response_code($code);
            $this->result_list = $result_list;
        }
    }
}