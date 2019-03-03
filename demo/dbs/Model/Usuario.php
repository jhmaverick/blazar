<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Model;

use Blazar\Component\Dao\CRUDMysql;
use Blazar\Component\Dao\DaoException;
use Blazar\Core\Log;
use Blazar\Core\Manifest;

class Usuario extends CRUDMysql {
    const VERSAO = 1;

    const TABLE = 'usuario';
    const COLS_PREFIX = 'user';

    const COL_ID = 'user_id';
    const COL_NOME = 'user_nome';
    const COL_EMAIL = 'user_email';
    const COL_TELEFONE = 'user_telefone';

    const SYSTEM_USER_ID = 1;

    public function __construct() {
        parent::__construct(Manifest::db('main_db'));
    }

    public function adicionar(array $valores) {
        $valores = $this->addPrefix($valores);

        if (!$this->checkStruct($valores)) {
            throw new DaoException('Os parametros não seguem a estrutura da tabela.');
        }

        $retorno = 0;

        try {
            $data = [
                self::COL_NOME => $valores[self::COL_NOME],
                self::COL_EMAIL => $valores[self::COL_EMAIL],
                self::COL_TELEFONE => (isset($valores[self::COL_TELEFONE])) ? $valores[self::COL_TELEFONE] : null,
            ];

            // O id do Usuario
            $retorno = $this->create(self::TABLE, $data);
        } catch (DaoException $e) {
            Log::d('Model Usuario', $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return $retorno;
    }

    public function alterar(array $valores): bool {
        $valores = $this->addPrefix($valores);

        if (!$this->checkStruct($valores)) {
            throw new DaoException('Os parametros não seguem a estrutura da tabela.');
        }

        try {
            $data = [
                self::COL_NOME => $valores[self::COL_NOME],
                self::COL_EMAIL => $valores[self::COL_EMAIL],
                self::COL_TELEFONE => (isset($valores[self::COL_TELEFONE])) ? $valores[self::COL_TELEFONE] : null,
            ];

            $where_con = self::COL_ID . ' = ?';
            $where_val = [$valores[self::COL_ID]];

            return $this->update(self::TABLE, $data, $where_con, $where_val);
        } catch (DaoException $e) {
            Log::d('Model Usuario', $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return false;
    }

    public function listar() {
        $retorno = [];

        try {
            $campos = $this->getStringColumnsExcept([self::COL_TELEFONE], 'u');
            $where_con = self::COL_ID . ' > ?';
            $val = [1];
            $ordem = [self::COL_ID => 'desc'];
            $limit = 10;

            $retorno = $this->read(self::TABLE . ' u', $campos, $where_con, $val, null, null, $ordem, $limit);
        } catch (DaoException $e) {
            Log::e('Model Usuario', $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return $retorno;
    }

    public function remover(int $id) {
        $retorno = null;

        try {
            $where_con = self::COL_ID . ' = ?';
            $where_val = [$id];

            $retorno = $this->delete(self::TABLE, $where_con, $where_val);
        } catch (DaoException $e) {
            Log::e('Model Usuario', $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return $retorno;
    }
}