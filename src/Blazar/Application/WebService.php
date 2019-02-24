<?php

/**
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source
 * code.
 */

namespace Blazar\Application;

use Blazar\System\ClassMap;
use Blazar\System\Log;
use Blazar\System\Manifest;
use Blazar\Util\StrRes;
use Exception;
use ReflectionMethod;

/**
 * Classe para disponibilizar APIs em rede atraves de requisições GET e POST.
 *
 * As APIs disponíveis devem estar em manifest>map<br>
 * <br>
 * Exemplo de requisições:<br>
 * * common: .../api_map_name/?method=(method_name)&(param1)=(value)&(param2)=(value)&...<br>
 * * multi_request:
 * .../?multi_request&params={(request_key1):{method:(api_map_name/method_name),(param1):(value),(param2)=(value)},
 * (request_key2):{method:(api_map_name/method_name),(param1):(value),(param2)=(value)}, ...}<br>
 * * long_polling:
 * .../?long_polling&params={(request_key1):{method:(api_map_name/method_name),(param1):(value),(param2)=(value)},
 * (request_key2):{method:(api_map_name/method_name),(param1):(value),(param2)=(value)}, ...}<br>
 * <br>
 * Os métodos das APIs irão receber os seguintes parâmetros:<br>
 * #0 array [data] As informações da requisição.<br>
 * #1 array|null [result_list] (Apenas para multi_request e long_polling) A lista de resultados gerados pelas
 * requisições anteriores
 * #2 object|null [instance] (Apenas para webservice) A instancia da classe webservice.<br>
 */
class WebService {

    private static $default_method_param = "method";
    private static $started = false;

    private $method_param;
    private $webservice_controller;
    private $current_map;
    private $api_map;
    private $view;
    private $inherited_enable = false;

    // Retornos dos metodos de multiplas requisições
    private $result_list = [];

    /**
     * WebService constructor
     *
     * Define automaticamente qual é o tipo de requisição dependo dos parametros informados.<br>
     * <br>
     * Esta classe deve ser chamada apenas em classes listadas no map do manifest.<br>
     * Para utilizar com multiplas apis, a classe controladora deve ser a ultima a ser chamada no map para que a API
     * consiga identificar os descendentes dela.
     *
     * @param bool $webservice_controller <p>
     * A classe é um Webservice para controlar APIs cadastradas no seu "sub" no manifest.<br>
     * Se true irá trabalhar com as classes descendentes da herdeira informadas no map->sub.<br>
     * Se false apenas os métodos da classe herdeira serão validos.
     * </p>
     * @param string $method_param <p>
     * Ação padrão para a chamada dos métodos das requisições<br>
     * A classe API deve possuir um método com o nome enviado pelo parametro<br>
     * <br>
     * Ex: .../user/?method=show_name<br>
     * <code>public function showName() {...</code
     * </p>
     * @param bool $inherited_enable Habilitar uso de métodos herdados
     */
    public function __construct(bool $webservice_controller = false, string $method_param = null, bool $inherited_enable = false) {
        // Evita que 2 Webservices sejam iniciados em uma mesma requisição
        if (self::$started) return;
        self::$started = true;

        $this->webservice_controller = $webservice_controller;
        $this->method_param = $method_param ?? self::$default_method_param;
        $this->inherited_enable = $inherited_enable;

        $this->current_map = ClassMap::current();
        $this->api_map = Manifest::map($this->current_map["route"]);
        $this->view = new View();

        $request_data = self::getRequestData();

        try {
            // Verifica qual tipo de requisição vai acontecer
            if (isset($request_data['multi_request'])) {
                $this->multiRequest($request_data);
            } else if (isset($request_data['long_polling'])) {
                $this->longPolling($request_data);
            } else if (isset($request_data[$this->method_param]) && is_string($request_data[$this->method_param])) {
                $this->requestCommon($request_data);
            } else {
                $this->result_list = "Nenhuma ação foi passada para a API";
            }

            // Prepara o retorno
            if (isset($request_data['multi_request'])) {
                $this->view->reset($this->result_list);
            } else if (isset($request_data['long_polling'])) {
                $this->view->reset($this->result_list);
            } else {
                $this->view->reset($this->result_list);
            }
        } catch (Exception $e) {
            Log::e("Erro no gerenciador de APIs", $e);
            $this->view->reset("Não foi possível carregar a API");
        }

        $this->view->render();
    }

    /**
     * Define qual será o parâmetro padrão que irá guardar o método a ser chamado nas requisições
     *
     * @param string $default_method_param
     */
    public static function setDefaultMethodParam(string $default_method_param): void {
        self::$default_method_param = $default_method_param;
    }

    /**
     * Obtem os dados da requisição passados por post ou get.
     *
     * @param bool $merge <p>
     * Mesclar dados enviados por GET e POST.<br>
     * Os dados do POST terão prioridade.
     * </p>
     *
     * @return array
     */
    protected function getRequestData(bool $merge = true): array {
        $request_data = [];

        $requests = [];
        if ($merge === true) $requests = [$_GET, $_POST];
        else if ($_SERVER['REQUEST_METHOD'] == "POST") $requests[] = $_POST;
        else if ($_SERVER['REQUEST_METHOD'] == "GET") $requests[] = $_GET;

        foreach ($requests as $request_method) {
            foreach ($request_method as $index => $value) {
                $request_data[$index] = $value;
            }
        }

        return $request_data;
    }

    /**
     * Metodo de requisição comum
     *
     * @param array $request_data
     *
     * @throws Exception
     */
    private function requestCommon(array $request_data) {
        try {
            // Pega o nome do método e remove ele da lista
            $method = $request_data[$this->method_param];
            unset($request_data[$this->method_param]);

            // Verifica se deve executar apenas os metodos da classe
            if ($this->webservice_controller === false) {
                $webservice = null;

                $api_class = get_class($this);
                $api = $this;
            } else {
                $webservice = $this;

                // Pega o parametro da URL para obter a classe
                $api_class = ClassMap::next("class");
                if ($api_class == null || !class_exists($api_class)) {
                    $this->result_list = "API não existe";
                    return;
                }

                $api = new $api_class();
            }

            // Transforma a ação no padrão do metodo
            $metodo = $this->param2method($method);

            if ($this->methodValidate($api_class, $metodo))
                $this->result_list = call_user_func_array([$api, $metodo], [$request_data, null, $webservice]);
            else $this->result_list = "Ação não encontrada";
        } catch (Exception $e) {
            Log::e($e);
            $this->result_list = "Não foi possível retornar os dados da API";
        }
    }

    /**
     * Metodo de requisição para multiplas APIs
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
            if (isset($v[$this->method_param]) && substr_count($v[$this->method_param], "/") == 1) {
                // Separa a API da ação
                list($map_name, $method_name) = explode("/", $v[$this->method_param]);
                unset($v[$this->method_param]);

                try {
                    if ($this->webservice_controller === false) {
                        $webservice = null;

                        $api_class = get_class($this);
                        $api = $this;
                    } else {
                        $webservice = $this;

                        // Verifica se a API existe
                        $api_class = $this->api_map['sub'][$map_name]['class'] ?? null;
                        if ($api_class == null || !class_exists($api_class)) {
                            $this->result_list[$i] = "API não existe";
                            continue;
                        }

                        $api = new $api_class();
                    }

                    // Transforma a ação no padrão do metodo
                    $method = $this->param2method($method_name);

                    if ($this->methodValidate($api_class, $method))
                        $this->result_list[$i] = call_user_func_array([$api, $method], [$v, $this->result_list, $webservice]);
                    else $this->result_list[$i] = "Ação não encontrada na API";
                } catch (Exception $e) {
                    Log::e($e);
                    $this->result_list[$i] = "Não foi possível retornar os dados da API";
                }
            }
        }
    }

    /**
     * Metodo de requisição por long polling
     *
     * @param $data
     *
     * @throws Exception
     */
    private function longPolling($data) {
        if (!isset($data['params']) || !is_array(json_decode($data['params'], true))) {
            return;
        }

        // Tempo maximo para a execução
        $tempo_limite = 20;

        // Seta a execução do php com 10 segundo a mais do que o longpoll para não parar um script pela metade
        ini_set("max_execution_time", $tempo_limite + 10);

        $data['params'] = json_decode($data['params'], true);

        while (true) {
            $atualizacao = false;

            foreach ($data['params'] as $i => $v) {
                if (isset($v[$this->method_param]) && substr_count($v[$this->method_param], "/") == 1) {
                    // Separa a API da ação
                    list($map_name, $method_name) = explode("/", $v[$this->method_param]);
                    unset($v[$this->method_param]);

                    try {
                        if ($this->webservice_controller === false) {
                            $webservice = null;

                            $api_class = get_class($this);
                            $api = $this;
                        } else {
                            $webservice = $this;

                            // Verifica se a API existe
                            $api_class = $this->api_map['sub'][$map_name]['class'] ?? null;
                            if ($api_class == null || !class_exists($api_class)) {
                                $this->result_list[$i] = "API não existe";
                                continue;
                            }

                            $api = new $api_class();
                        }

                        // Transforma a ação no padrão do metodo
                        $method = $this->param2method($method_name);

                        // TODO colocar um array na classe informando se o metodo aceita o long pool e que tipo retorno deve ser considerado como atualização
                        if ($this->methodValidate($api_class, $method)) {
                            $this->result_list[$i] = call_user_func_array([$api, $method], [$v, $this->result_list, $webservice]);

                            // Verifica se existe um dado para ser retornado
                            if (is_array($this->result_list[$i]) && count($this->result_list[$i]) > 0)
                                $atualizacao = true;
                        } else $this->result_list[$i] = "Ação não encontrada na API";
                    } catch (Exception $e) {
                        Log::e($e);
                        $this->result_list[$i] = "Não foi possível retornar os dados da API";
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
     * Verifica se é um método valido
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
        if (!StrRes::startsWith($method, "__") && method_exists($class_name, $method)) {
            // Verifica se o metodo é publico
            $reflection = new ReflectionMethod($class_name, $method);

            if ($reflection->isPublic() &&
                $reflection->class !== __CLASS__ &&
                ($this->inherited_enable || $reflection->class === $class_name)
            ) {
                return true;
            } else {
                return false;
            }
        }
        return false;
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
    private function param2method(string $nome) {
        $nome = ucwords(str_replace("_", " ", $nome));
        $nome = str_replace(" ", "", $nome);

        return lcfirst($nome);
    }

}