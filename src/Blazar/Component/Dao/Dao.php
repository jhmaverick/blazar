<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\Dao;

use Blazar\Component\TypeRes\StrRes;
use Blazar\Core\Log;
use Error;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionClass;
use ReflectionException;

/**
 * Responsavel pelo controle de acesso ao Banco de dados.
 */
abstract class Dao {

    /**#@+
     * Tipos de consulta para forçar o método prepare a forçar o retorno
     */
    const PREPARE_DEFAULT = 0;
    const PREPARE_SELECT = 1;
    const PREPARE_INSERT = 2;
    /**#@-*/

    /**
     * Conexão em aberta no PDO.
     * @var PDO
     */
    protected $con = null;
    /**
     * Statement.
     * @var PDOStatement|false
     */
    protected $stmt = null;

    // Esse atributo guarda os campos da tabela após a classe ser instanciada.
    private $columns = null;
    // Informa se a conexão foi aberta automaticamente ou manualmente
    private $auto_open_close = false;
    // Lista de conexões abertas
    private static $opened_connection = [];
    // Isto possibilita classes compartilharem a mesma conexão em conjunto com atributo $opened_connection
    private $key_connection = '';
    // Dados da conexão atual
    private $data_connection = [
        'driver' => 'mysql',
        'host' => null,
        'user' => null,
        'pass' => null,
        'db' => null,
        'port' => null,
        'socket' => null,
    ];

    /**
     * Dao constructor.
     *
     * @param array $dados Os dados da conexão
     * @param bool $cc Gera uma conexão compartilhada com outras classes que estão acessando o mesmo db
     */
    public function __construct(array $dados, bool $cc = true) {
        try {
            $this->setDataConnection($dados, $cc);

            // Força a instancia a salvar os campos da tabela no atributo
            $this->prepareColumns();
        } catch (DaoException $e) {
            Log::e($e);
        }
    }

    /**
     * Verifica se a coluna existe na tabela.
     *
     * @param string $column
     *
     * @return bool
     */
    public function hasColumn($column) {
        $list = $this->columns;

        if (in_array($column, $list)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Lista de campos da tabela.
     *
     * @return array <p>
     * Retorna a lista com os nomes das constantes com o index e seu valor.<br>
     * Ex: [COL_ID => "id", COL_NOME => "nome", ...]
     * </p>
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * Retorna uma string com as colunas da tabela pronta para uso em um select.
     *
     * @param array|string|null $columns <p>
     * Array com as constantes das colunas
     * Caso deseje retornar com "AS" então adicione a constante no index e a nomeação no valor
     * Se passado como null ou "*" então retorna todas as colunas
     * </p>
     * @param string|null $tb_name O nome que a tabela esta usando na query para ser adicionado em cada coluna, padrão
     *     [a-zA-Z0-9_]
     * @param bool $as_array Retornar como array
     *
     * @return array|string
     * @throws DaoException
     */
    public function getStringColumns($columns = null, $tb_name = null, $as_array = false) {
        if ($tb_name !== null && preg_match('/^([a-zA-Z0-9_])+$/', $tb_name) == 0) {
            throw new DaoException('tb_name invalido. o padrão aceito é [a-zA-Z0-9_].');
        }

        $list = $this->columns;

        if ($columns == null || (is_array($columns) && count($columns) == 0) || $columns === '*') {
            $columns = [];

            foreach ($list as $i => $v) {
                $columns[] = $v;
            }
        }

        $list_columns = [];

        foreach ($columns as $i => $v) {
            $valor = is_numeric($i) ? $v : $i;
            $as = is_numeric($i) ? null : $v;

            if (in_array($valor, $list)) {
                $temp = ($tb_name != null ? $tb_name . '.' : '') .
                    $valor .
                    ($as != null ? ' AS ' . $as : '');

                $list_columns[] = $temp;
            } else {
                throw new DaoException('A coluna "' . $valor .
                    '" não existe na classe de representação da tabela "' . $this->getConst('TABLE') . '"');
            }
        }

        if ($as_array) {
            return $list_columns;
        } else {
            return implode(', ', $list_columns);
        }
    }

    /**
     * Retorna uma string com as colunas da tabela que não estão na lista passada pronta para uso em um select.
     *
     * @param array $columns Array com as constantes das colunas que não serão retornadas
     * @param string|null $tb_name O nome que a tabela esta usando na query para ser adicionado em cada coluna, padrão
     *     [a-zA-Z0-9_]
     * @param bool $as_array Retornar como array
     *
     * @return array|string
     * @throws DaoException
     */
    public function getStringColumnsExcept(array $columns, $tb_name = null, $as_array = false) {
        if ($tb_name !== null && preg_match('/^([a-zA-Z0-9_])+$/', $tb_name) == 0) {
            throw new DaoException('tb_name invalido. o padrão aceito é [a-zA-Z0-9_].');
        }

        $list = $this->columns;

        foreach ($columns as $i => $v) {
            if (!in_array($v, $list)) {
                throw new DaoException('A coluna "' . $v .
                    '" não existe na classe de representação da tabela "' . $this->getConst('TABLE') . '"');
            }
        }

        $list_columns = [];

        foreach ($list as $i => $valor) {
            if (!in_array($valor, $columns)) {
                $temp = ($tb_name != null ? $tb_name . '.' : '') . $valor;

                $list_columns[] = $temp;
            }
        }

        if ($as_array) {
            return $list_columns;
        } else {
            return implode(', ', $list_columns);
        }
    }

    /**
     * Verifica se a estrutura segue o padrão da tabela.
     *
     * @param array $dados [index => valor, index => valor...]
     * @param bool $completa Verifica se não esta faltando indexes
     *
     * @return bool
     */
    public function checkStruct(array $dados, bool $completa = false) {
        $list = $this->columns;

        foreach ($dados as $i => $v) {
            if (!in_array($i, $list)) {
                return false;
            }
        }

        if ($completa && count($dados) != count($list)) {
            return false;
        }

        return true;
    }

    /**
     * Retorna o array com a estrutura da tabela.
     *
     * As colunas viram indexes e recebem null
     *
     * @param array $dados Se vazio ele gera a estrutura
     * @param array $add_data Mescla uma lista dentro da estrutura principal
     *
     * @return array
     * @throws DaoException
     */
    public function tableStruct(array $dados = [], array $add_data = []) {
        if (count($dados) > 0 && !$this->checkStruct($dados, true)) {
            throw new DaoException('A estrutura de dados não segue o padrão da tabela "' . $this->getConst('TABLE') . '".');
        }

        if (count($add_data) > 0 && !$this->checkStruct($add_data)) {
            throw new DaoException('Os dados para adicionar não seguem o padrão da tabela "' . $this->getConst('TABLE') . '".');
        }

        $list = $this->columns;

        $list_columns = [];

        foreach ($list as $i => $v) {
            $list_columns[$v] = null;
        }

        foreach ($add_data as $i => $v) {
            $list_columns[$i] = $v;
        }

        return $list_columns;
    }

    /**
     * Remove os indices que não pertencem a tabela.
     *
     * @param array $dados [index => valor, index => valor...]
     *
     * @return array
     */
    public function cleanStruct(array $dados) {
        $list = $this->columns;

        foreach ($dados as $i => $v) {
            if (!in_array($i, $list)) {
                unset($dados[$i]);
            }
        }

        return $dados;
    }

    /**
     * Aplica o pré-fixo da tabela em indices que ainda não possuem.
     *
     * @example <code>
     * const TABLE = "usuario";<br>
     * const PREFIX = "user";<br>
     * <br>
     * $this->addPrefix([nome => "Meu Nome", idade => 25]);<br>
     * Retorna [user_nome => "Menu nome", user_idade => 25]
     * </code>
     *
     * @param string|string[] $dados <p>
     * O prefixo só é removido se existir um campo na tabela com o nome.<br>
     * Se for uma string retorna:<br>
     * Ex: "nome" retorna "user_nome".<br><br>
     * Se for um array sequencial retorna:<br>
     * Ex: ["id", "nome"] retorna ["user_id", "user_nome"].<br><br>
     * Se for um array:<br>
     * Ex: ["id" => 1, "nome" => "João"] retorna ["user_id" => 1, "user_nome" => "João"].
     * </p>
     * @param bool $replace_indices <p>
     * Se true substitui indices que duplicarem na tabela<br>
     * Ex: [user_id => 20, id => 1000] retorna [user_id => 1000]<br>
     * Se false mantem os originais.
     * </p>
     *
     * @return mixed
     * @throws DaoException
     */
    public function addPrefix($dados, bool $replace_indices = false): array {
        $pre = $this->getConst('COLS_PREFIX');

        if ($pre === null) {
            return $dados;
        }
        $pre = $pre . '_';

        // Verifica se é apenas uma string
        if (gettype($dados) === 'string') {
            if (in_array($pre . $dados, $this->columns)) {
                return $pre . $dados;
            } else {
                return $dados;
            }
        } elseif (!is_array($dados)) {
            throw new DaoException('Valor informado deve ser um array ou string');
        }

        // Verifica se o array esta vazio
        if (count($dados) == 0) {
            return $dados;
        }

        // Verifica se os indices são sequenciais
        // Aplicado em casos onde é necessario apenas tratar indices
        if (array_values($dados) === $dados) {
            foreach ($dados as $nome => $valor) {
                // Novo nome com o prefixo
                $novo_valor = $pre . $valor;

                // Verifica se o indice é uma coluna da tabela
                // Verifica se no array já não existe um indice com o novo nome ou se deve substituir
                if (
                    in_array($novo_valor, $this->columns) &&
                    (!in_array($novo_valor, $dados) || $replace_indices)
                ) {
                    $dados[$nome] = $novo_valor;
                } elseif (!in_array($valor, $dados)) {
                    $dados[$nome] = $valor;
                }
            }

            return $dados;
        } // Aplicado em casos de indicies com valores
        else {
            $novo = [];
            foreach ($dados as $nome => $valor) {
                // Novo nome com o prefixo
                $novo_nome = $pre . $nome;

                // Verifica se o indice com o prefixo é uma coluna da tabela
                // Verifica se no array já não existe um indice com o novo nome ou se deve substituir
                if (in_array($novo_nome, $this->columns) &&
                    (!isset($novo[$novo_nome]) || $replace_indices)
                ) {
                    $novo[$novo_nome] = $valor;
                } elseif (!isset($novo[$nome])) {
                    $novo[$nome] = $valor;
                }
            }

            return $novo;
        }
    }

    /**
     * Remove os pré-fixos da tabela em indices.
     *
     * @example <code>
     * const TABLE = "usuario";<br>
     * const PREFIX = "user";<br>
     * <br>
     * $this->removePrefix([user_nome => "Meu Nome", user_idade => 25]);<br>
     * Retorna [nome => "Menu nome", idade => 25]
     * </code>
     *
     * Apenas os prefixos da tabela serão removidos, resultados retornados por JOIN continuarão com seus prefixos.<br>
     * Se for necessario remover os prefixos desses resultados, esse metodo deve ser chamado atraves da instancia da
     *     classe responsavel.
     *
     * @param string|array[]|string[] $dados <p>
     * O prefixo só é removido se existir um campo na tabela com o nome.<br>
     * Se for uma string retorna.<br>
     * Ex: "user_nome" retorna "nome".<br><br>
     * Se for um array sequencial retorna.<br>
     * Ex: ["user_id", "user_nome"] retorna ["id", "nome"].<br><br>
     * Se for dados em um vetor.<br>
     * Ex: ["user_id" => 1, "user_nome" => "João"] retorna ["id" => 1, "nome" => "João"].<br><br>
     * Se for dados em uma matriz.<br>
     * Ex: [["user_id" => 1, "user_nome" => "João"], ["user_id" => 2, "user_nome" => "José"]]<br>
     * retorna [["id" => 1, "nome" => "João"], ["id" => 2, "nome" => "José"]].<br><br>
     * </p>
     * @param bool $replace_indices <p>
     * Se true substitui indices que duplicarem na tabela<br>
     * Ex: [user_id => 20, id => 1000] retorna [id => 20]<br>
     * Se false mantem o prefixo dos que duplicarem.
     * </p>
     *
     * @return array|string
     * @throws DaoException
     */
    public function removePrefix($dados, bool $replace_indices = false) {
        $pre = $this->getConst('COLS_PREFIX');

        // Se a tabela na tiver a constante PREFIX retorna o array como esta
        if ($pre === null) {
            return $dados;
        }
        $pre = $pre . '_';

        // Verifica se é apenas uma string
        if (gettype($dados) === 'string') {
            $novo_valor = StrRes::replaceFirst($dados, $pre, '');

            if (in_array($novo_valor, $this->columns)) {
                return $novo_valor;
            } else {
                return $dados;
            }
        } elseif (!is_array($dados)) {
            throw new DaoException('Valor informado deve ser um array ou string');
        }

        // Verifica se o array esta vazio
        if (count($dados) == 0) {
            return $dados;
        }

        // Verifica se é um arrau multidimensional
        $multidimensional = count(array_filter($dados, 'is_array')) > 0;

        // Verifica se não é um array multidimensional e se os indices são sequenciais
        // Aplicado em casos onde é necessario apenas tratar indices
        if (!$multidimensional && array_values($dados) === $dados) {
            foreach ($dados as $nome => $valor) {
                $novo_valor = StrRes::replaceFirst($valor, $pre, '');

                // Verifica se o indice é uma coluna da tabela
                // Verifica se no array já não existe um indice com o novo nome ou se deve substituir
                if (
                    in_array($novo_valor, $this->columns) &&
                    (!in_array($novo_valor, $dados) || $replace_indices)
                ) {
                    $dados[$nome] = $novo_valor;
                } elseif (!in_array($valor, $dados)) {
                    $dados[$nome] = $valor;
                }
            }

            return $dados;
        } // Aplicado em casos de indicies com valores
        else {
            // Verifica se é um array multidimensional
            $list = $multidimensional ? $dados : [$dados];

            $novo = [];
            foreach ($list as $index => $ln) {
                $novo[$index] = [];

                foreach ($ln as $nome => $valor) {
                    // Novo nome sem o prefixo
                    $novo_nome = StrRes::replaceFirst($nome, $pre, '');

                    // Verifica se o indice é uma coluna da tabela
                    // Verifica se no array já não existe um indice com o novo nome ou se deve substituir
                    if (in_array($nome, $this->columns) &&
                        (!isset($novo[$index][$novo_nome]) || $replace_indices)
                    ) {
                        $novo[$index][$novo_nome] = $valor;
                    } elseif (!isset($novo[$index][$nome])) {
                        $novo[$index][$nome] = $valor;
                    }
                }
            }

            // Verifica se o valor que chegou era um array de array
            return ($multidimensional) ? $novo : $novo[0];
        }
    }

    /**
     * Prepara a ordenação de uma tabela para buscas.
     *
     * @param array $dados [coluna1, coluna2 => "desc", coluna3]
     *
     * @return string|null
     * @throws DaoException
     */
    public function stringOrderBy(array $dados) {
        $final_ordem = null;

        if (count($dados) > 0) {
            $list_ordenar = [];

            foreach ($dados as $i => $v) {
                $column = is_numeric($i) ? $v : $i;
                $order = strtolower(is_numeric($i) ? 'asc' : $v);

                if ($order != 'asc' && $order != 'desc') {
                    throw new DaoException('Ordem inválida ' . $column);
                }

                if ($order == 'desc') {
                    $list_ordenar[] = $column . ' DESC';
                } else {
                    $list_ordenar[] = $column;
                }
            }

            $final_ordem = implode(', ', $list_ordenar);
        }

        return $final_ordem;
    }

    /**
     * Trata valores do limit contra sql injection.
     *
     * @param string|int $limit
     *
     * @return bool Retorna true se for um limit valido
     */
    public function checkLimit($limit) {
        if (substr_count($limit, ',') > 1) {
            $error_limit = true;
        }

        if (substr_count($limit, ',') == 1) {
            $tlimit = explode(',', $limit);
            $tlimit[0] = trim($tlimit[0]);
            $tlimit[1] = trim($tlimit[1]);

            if (!is_numeric($tlimit[0])) {
                $error_limit = true;
            } elseif (!is_numeric($tlimit[1])) {
                $error_limit = true;
            }
        } elseif (substr_count($limit, ',') == 0) {
            if ($limit != null && !is_numeric(trim($limit))) {
                $error_limit = true;
            }
        }

        if (isset($error_limit)) {
            return false;
        }

        return true;
    }

    /**
     * Forma generica de executar uma consulta com prepare.
     *
     * @param string $sql
     * @param array $where_val
     * @param int $force self::PREPARE_SELECT ou self::PREPARE_INSERT
     *
     * @return array|bool|int
     * @throws DaoException
     */
    protected function prepare(string $sql, array $where_val = [], int $force = self::PREPARE_DEFAULT) {
        if ($this->stmt = $this->con->prepare($sql)) {
            $i = 1;
            while ($i <= count($where_val)) {
                $this->stmt->bindValue($i, $where_val[$i - 1]);
                $i++;
            }

            $result = $this->stmt->execute();
        } else {
            throw new DaoException('Erro de conexão ' . $this->con->errorInfo()[2]);
        }

        $final = null;
        if ($force == self::PREPARE_SELECT || StrRes::startsWith(strtolower(trim($sql)), 'select')) {
            $final = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($force == self::PREPARE_INSERT || StrRes::startsWith(strtolower(trim($sql)), 'insert')) {
            if ($this->stmt->rowCount() > 0) {
                $id = $this->con->lastInsertId();

                // Verifica se o id e auto increment.
                // Se tiver auto increment retorna o inteiro
                // e se não, retorna true para indicar sucesso
                if ($id > 0) {
                    $final = $id;
                } else {
                    $final = true;
                }
            } else {
                $final = $result;
            }
        } else {
            $final = $result;
        }

        return $final;
    }

    /**
     * Inicia uma conexão apenas se ela não existir.
     * @throws DaoException
     */
    protected function autoOpenDB() {
        if ($this->con === null && self::openedConnection($this->key_connection) !== false) {
            $this->con = self::openedConnection($this->key_connection);
        } elseif ($this->con === null && self::openedConnection($this->key_connection) === false) {
            self::openDB();
            $this->auto_open_close = true;
        }
    }

    /**
     * Caso a conexão tenha sido iniciada pelo metodo autoOpenDB ele a encerra.
     * @throws DaoException
     */
    protected function autoCloseDB() {
        if ($this->con !== null && $this->auto_open_close === true) {
            self::closeDB();
            $this->auto_open_close = false;
        }
    }

    /**
     * Abre Conexão.
     *
     * @param bool $disable_autocommit Desabilitar auto commit
     *
     * @throws DaoException
     */
    final protected function openDB(bool $disable_autocommit = false) {
        if ($this->con) {
            throw new DaoException('Já existe uma conexão aberta.');
        }

        if (self::openedConnection($this->key_connection) !== false) {
            $this->con = self::openedConnection($this->key_connection);
        } else {
            try {
                $dsn = $this->data_connection['drive'] . ':';

                //Verifica se a conexão será por socket ou hostname/IP
                if (isset($this->data_connection['socket'])) {
                    $dsn .= 'unix_socket=' . $this->data_connection['socket'] . ';';
                } else {
                    $dsn .= 'host=' . $this->data_connection['host'] . ';';
                }

                //Verifica se há porta para conexão
                if (isset($this->data_connection['port'])) {
                    $dsn .= 'port=' . $this->data_connection['port'] . ';';
                }

                //Escreve o nome do banco de dados
                $dsn .= 'dbname=' . $this->data_connection['db'] . ';';
                $dsn .= 'charset=utf8mb4;';

                //Define utf8 como a formatação padrão de caracteres
                $options = [
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ];

                //Habilita o auto-commit
                if (!$disable_autocommit) {
                    $options[PDO::ATTR_PERSISTENT] = true;
                }

                //Instancia o objeto PDO
                @$this->con = new PDO($dsn,
                    $this->data_connection['user'],
                    $this->data_connection['pass'],
                    $options
                );

                if ($disable_autocommit) {
                    $this->con->beginTransaction();
                }

                self::openedConnection($this->key_connection, $this->con);
            } catch (Error $e) {
                throw new DaoException('Não foi possível conectar-se ao banco de dados');
            } catch (PDOException $e) {
                throw new DaoException($e->getMessage() . '=PDO: Não foi possível conectar-se ao banco de dados');
            }
        }
    }

    /**
     * Fecha Conexão.
     *
     * @throws DaoException
     */
    final protected function closeDB() {
        if ($this->con === null) {
            throw new DaoException('Nenhuma conexão aberta para fechar.');
        }

        if ($this->stmt !== null) {
            if ($this->stmt) {
                $this->stmt->closeCursor();
            }
            $this->stmt = null;
        }

        self::openedConnection($this->key_connection, 'delete');

        $this->con = null;
    }

    /**
     * Retorna o valor de uma constant.
     *
     * @param string $name
     *
     * @return mixed retorna null se não for definida
     */
    protected function getConst($name) {
        $class = get_class($this);

        return defined($class . '::' . $name) ? constant($class . '::' . $name) : null;
    }

    /**
     * Adiciona ou pega uma conexão.
     *
     * Se $object for = null retorna uma conexão
     * Se $object for = "delete" remove o indice
     *
     * @param string $key A chave de identificação da conexão
     * @param PDO $object Os dados ou a operação
     *
     * @return bool|PDO
     * @throws DaoException
     * @throws DaoException
     */
    private static function openedConnection($key, $object = null) {
        if ($object === null) {
            if (isset(self::$opened_connection[$key])) {
                return self::$opened_connection[$key];
            } else {
                return false;
            }
        } elseif ($object == 'delete') {
            if (isset(self::$opened_connection[$key])) {
                unset(self::$opened_connection[$key]);

                return true;
            } else {
                return false;
            }
        } else {
            if (is_a($object, 'PDO')) {
                self::$opened_connection[$key] = $object;

                return true;
            } else {
                throw new DaoException('Object deve ser uma instancia PDO');
            }
        }
    }

    /**
     * Seta dados da conexão.
     *
     * @param array $dados <p>
     *      [host => string, user => string, pass => string, db => string, [port] => int, [socket] => string]
     * </p>
     * @param bool $cc Gera uma conexão compartilhada com outras classes que estão acessando o mesmo db
     *
     * @throws DaoException
     */
    final private function setDataConnection(array $dados, bool $cc = true) {
        if (isset($dados['host']) && isset($dados['user']) && isset($dados['pass']) && isset($dados['db'])) {
            $key_id_db = $dados['host'] . $dados['user'] . $dados['pass'] . $dados['db'];

            $this->data_connection['drive'] = $dados['drive'] ?? 'mysql';
            $this->data_connection['host'] = $dados['host'];
            $this->data_connection['user'] = $dados['user'];
            $this->data_connection['pass'] = $dados['pass'];
            $this->data_connection['db'] = $dados['db'];

            if (isset($dados['port'])) {
                $this->data_connection['port'] = $dados['port'];
                $key_id_db .= $dados['port'];
            }

            if (isset($dados['socket'])) {
                $this->data_connection['socket'] = $dados['socket'];
                $key_id_db .= $dados['socket'];
            }

            if (!$cc) {
                $key_id_db .= microtime();
            }

            $this->key_connection = md5($key_id_db);
        } else {
            throw new DaoException('Dados de conexão inválidos.');
        }
    }

    /**
     * Lista de campos da tabela.
     *
     * Salva a lista com os nomes das constantes com o index e seu valor no atributo "columns".<br>
     * Ex: [COL_ID => "id", COL_NOME => "nome", ...]
     *
     * @throws DaoException
     */
    private function prepareColumns() {
        $list_columns = [];

        try {
            $reflect = new ReflectionClass(get_class($this));
            $constants = $reflect->getConstants();

            foreach ($constants as $i => $v) {
                if (StrRes::startsWith($i, 'COL_')) {
                    $list_columns[$i] = $v;
                }
            }

            if (count($list_columns) == 0) {
                throw new DaoException('Nenhuma constante de coluna foi definida na classe de representação da ' .
                    'tabela ' . $this->getConst('TABLE') . ".\nDefina as colunas com prefixo \"COL_\".");
            }
        } catch (ReflectionException $re) {
            Log::e('ReflectionClass', $re);
        }

        $this->columns = $list_columns;
    }
}