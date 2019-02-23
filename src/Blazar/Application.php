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
use Throwable;

/**
 * Classe de gerenciamento de dados da aplicação
 */
class Application {

    /**
     * Parâmetro destinado a seleção de classes no mapa do manifest
     */
    const PARAMS_MAP = 2;
    /**
     * Parâmetro destinado a aplicação
     */
    const PARAMS_APP = 1;
    /**
     * Todos os parâmetros informados na URL
     */
    const PARAMS_ALL = 0;
    protected static $parameters = [];
    protected static $url_params = [];
    protected static $map_params = [];
    protected static $max_index_map = 0;
    private static $started = false;

    /**
     * Iniciar a partir do map de classes do Manifest
     *
     * @throws BlazarException
     */
    public static function init() {
        // Impede que a função seja iniciada mais de uma vez
        if (self::$started) throw new BlazarException("Metodo \Blazar\Application::init foi chamado novamente.");
        self::$started = true;

        try {
            if (count(Manifest::map()) > 0) {
                $MapClass = ClassMap::next('class');
                new $MapClass();
            } else {
                throw new BlazarException("Nenhuma aplicação para iniciar.");
            }
        } catch (Throwable $e) {
            Log::e("Alguma exceção não foi tratada e chegou ao root", $e);
            exit("Não foi possível concluir a operação. Por favor tente mais tarde.");
        }
    }

    /**
     * Parâmetros de execução do sistema com base nos dados da URL amigavel e do Manifest map.
     *
     * Os parâmetros omitidos na URL são retornados utilizando os mapas definidos como "main" no manifest.
     *
     * @param int|null $index <p>
     * Retorna o nome do parâmetro na posição informada.<br>
     * Se não for informado retorna todas.
     * </p>
     * @param int $type <p>
     * Quais são os parâmetros que serão retornados.<br>
     * {@see Application::PARAMS_ALL}, {@see Application::PARAMS_APP} e {@see Application::PARAMS_MAP}
     * </p>
     *
     * @return array|string|null <p>
     * O retorno depende do tipo do index informado.<br>
     * Se o index informado não for encontrado retorna null.
     * </p>
     */
    public static function param($index = null, int $type = self::PARAMS_ALL) {
        if ($type === self::PARAMS_ALL) {
            if ($index !== null) {
                if (isset(self::$parameters[$index])) {
                    return self::$parameters[$index];
                } else {
                    return null;
                }
            }

            return self::$parameters;
        } else if ($type === self::PARAMS_APP) {
            $url = self::param();

            $inicio = ClassMap::maxIndex() + 1;

            $params = [];
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
        } else if ($type === self::PARAMS_MAP) {
            $url = self::param();

            $fim = ClassMap::maxIndex();

            $params = [];
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
     * Retorna apenas os parâmetros informados por URL amigas.
     *
     * @param int|null $index
     *
     * @return array|string|null
     */
    public static function urlParam($index = null) {
        if ($index !== null) {
            if (isset(Application::$url_params[$index])) {
                return Application::$url_params[$index];
            } else {
                return null;
            }
        }

        return Application::$url_params;
    }
}