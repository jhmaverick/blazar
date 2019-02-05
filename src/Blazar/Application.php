<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar;

use Blazar\System\Log;
use Error;
use Exception;
use Throwable;

/**
 * Classe de gerenciamento de dados da aplicação
 */
class Application {
    /**
     * Parametros para no mapa de classes do sistema
     */
    const PARAMS_MAP = 2;
    /**
     * Parametros para uso na aplicação
     */
    const PARAMS_APP = 1;
    /**
     * Todos os parametros da URL
     */
    const PARAMS_FULL = 0;

    private static $url_parameters = [];
    private static $url_params_pre_manifest = [];
    private static $parameters_info = [];
    private static $final_index_system = 0;
    private static $last_auto_call_params = 0;

    /**
     * Configura o sistema antes dele ser iniciado
     */
    public static function prepare() {
        new Manifest();
    }

    /**
     * Iniciar framework
     */
    public static function init() {
        try {
            if (count(Manifest::getMap()) > 0) {
                $modulo_inicial = Application::getNextParameter()['class'];

                new $modulo_inicial();
            } else {
                throw new ApplicationException("Nenhuma aplicação para iniciar.");
            }
        } catch (Throwable|Exception|Error $e) {
            Log::e("Alguma exceção não foi tratada e chegou ao root", $e);
            exit("Não foi possível concluir a operação. Por favor tente mais tarde.");
        }
    }

    /**
     * Pegar ultimo index do array de parametros da url que e usado pelo mapa de classes
     *
     * @return int
     */
    public static function getFinalIndexMap() {
        return self::$final_index_system;
    }

    /**
     * Index ou lista de parametros da url
     *
     * Retorna o nome do parametro informado, para um retorno mais detalhado dos parametros do mapa de classes do sistema use {@see System::getParameterInfo()}
     *
     * @param int|null $index <p>
     * Retorna o nome do parametro na posição informada.<br>
     * Se não for informado retorna.<br>
     * </p>
     * @param int $tipo <p>
     * Quais são os parametros que serão retornados.<br>
     * {@see System::PARAMS_FULL}, {@see System::PARAMS_APP} e {@see System::PARAMS_MAP}
     * </p>
     *
     * @return array|string|null <p>
     * O retorno depende do tipo do index informado.<br>
     * Se o index informado não for encontrado retorna null.
     * </p>
     */
    public static function getParameter($index = null, $tipo = self::PARAMS_FULL) {
        if ($tipo === self::PARAMS_FULL) {
            if ($index !== null) {
                if (isset(self::$url_parameters[$index])) {
                    return self::$url_parameters[$index];
                } else {
                    return null;
                }
            }

            return self::$url_parameters;
        } else if ($tipo === self::PARAMS_APP) {
            $url = self::getParameter();

            $inicio = self::getFinalIndexMap() + 1;

            $params = array();
            for ($i = $inicio; $i < count($url); $i++) {
                $params[] = $url[$i];
            }

            if ($index !== null) {
                if (isset($params[$index])) {
                    return $params[$index];
                } else {
                    return null;
                }
            }

            return $params;
        } else if ($tipo === self::PARAMS_MAP) {
            $url = self::getParameter();

            $fim = self::getFinalIndexMap();

            $params = array();
            for ($i = 0; $i <= $fim; $i++) {
                $params[] = $url[$i];
            }

            if ($index !== null) {
                if (isset($params[$index])) {
                    return $params[$index];
                } else {
                    return null;
                }
            }

            return $params;
        } else {
            return null;
        }
    }

    /**
     * Informações do parametro do mapa de classes do sistema setadas no map do manifest
     *
     * @param int|string|null $index <p>
     * Se for <b>int</b>, retorna a posição parametro na url(A contagem começa a partir do 0).<br>
     * Se for <b>string</b>, retorna o parametro com o nome informado.
     * Se existir mais de um parametro com mesmo nome na arvore, será retornado o primeiro encontrado, para evitar
     * isto informe a árvore na string separando por barra Ex: "usuarios/usuarios".<br>
     * Se não for informado, retorna um array com os dados de todos os parametros.<br>
     * </p>
     *
     * @return array|null <p>
     * O retorno depende do tipo do index informado.<br>
     * Se o index informado não for encontrado retorna null.
     * </p>
     */
    public static function getParameterInfo($index = null) {
        if ($index !== null) {
            if (is_int($index)) {
                // Retorna os dados do index requisitado ou null se não existir
                return self::$parameters_info[$index] ?? null;
            } else {
                $arvore = explode("/", $index);
                $index = (count($arvore) > 1) ? $index = end($arvore) : $index;

                foreach (self::$parameters_info as $i => $v) {
                    // Verifica se é o index desejado e se ele é a ultima ocorrencia dele caso tenha sido definido com "/"
                    if ($v['name'] == $index && array_count_values($arvore)[$v['name']] == 1) {
                        return $v;
                    }

                    // Remove do array
                    if (($rm = array_search($v['name'], $arvore)) !== false) {
                        unset($arvore[$rm]);
                    }
                }

                // Se não existir um parametro com esse nome retorna null
                return null;
            }
        } else {
            // Retorna todos os parametros
            return self::$parameters_info;
        }
    }

    /**
     * Pega informações do parametro de mapa de classes seguindo uma sequencia.
     *
     * Para pegar uma posição especifica use {@see System::getParameterInfo()}.
     *
     * @param bool $last Se true, retorna o ultimo parametro usado pela chamada automatica.
     *
     * @return array|null Para mais informações veja a documentação de {@see System::getParameterInfo()}
     */
    public static function getNextParameter($last = false) {
        if ($last) {
            $param = self::getParameterInfo(self::$last_auto_call_params - 1);
        } else {
            $param = self::getParameterInfo(self::$last_auto_call_params);
            if ($param !== null) self::$last_auto_call_params++;
        }

        return $param;
    }

    /**
     * Index ou lista de parametros da url antes do manifesto
     *
     * @param int|null $index
     *
     * @return array|string|null
     */
    public static function getParamsPreManifest($index = null) {
        if ($index !== null) {
            if (isset(self::$url_params_pre_manifest[$index])) {
                return self::$url_params_pre_manifest[$index];
            } else {
                return null;
            }
        }

        return self::$url_params_pre_manifest;
    }

    /**
     * Informa qual foi o ultimo indice usado na chamada automatica de parametros
     *
     * @return int
     */
    public static function getLastAutoCallParams(): int {
        return self::$last_auto_call_params;
    }

    /**
     * Redefinir array de parametros da URL
     *
     * @param array $url_parameters
     */
    protected static function setUrlParameters(array $url_parameters) {
        self::$url_parameters = $url_parameters;
    }

    /**
     * Redefinir array de parametros da URL pré manifest
     *
     * @param array $url_params_pre_manifest
     */
    protected static function setUrlParamsPreManifest(array $url_params_pre_manifest) {
        self::$url_params_pre_manifest = $url_params_pre_manifest;
    }

    /**
     * Adicionar novo parametros a arvore de parametros da execução atual
     *
     * @param array $parameters_tree
     */
    protected static function addParametersTree(array $parameters_tree) {
        self::$parameters_info[] = $parameters_tree;
    }

    /**
     * Define o ultimo index dos parametros do sistema
     *
     * @param int $final_index_system
     */
    protected static function setFinalIndexSystem(int $final_index_system) {
        self::$final_index_system = $final_index_system;
    }
}