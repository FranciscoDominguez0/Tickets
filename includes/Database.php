<?php
/**
 * CONEXIÓN A BASE DE DATOS
 * Clase para gestionar la conexión a MySQL
 * Puerto: 33065
 * Usuario: root
 * Contraseña: 12345678
 */

class Database {
    private static $instance;
    private $connection;

    private function __construct() {
        $this->connection = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            DB_PORT
        );

        if ($this->connection->connect_error) {
            throw new Exception('Error de conexión: ' . $this->connection->connect_error);
        }

        $this->connection->set_charset('utf8mb4');
    }

    /**
     * Obtener instancia singleton
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Preparar sentencia
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /**
     * Ejecutar query con parámetros
     */
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    /**
     * Obtener un registro
     */
    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * Obtener múltiples registros
     */
    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Obtener último ID insertado
     */
    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    /**
     * Obtener número de filas afectadas
     */
    public function affectedRows() {
        return $this->connection->affected_rows;
    }

    /**
     * Escapar valor
     */
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }

    /**
     * Obtener tipos de parámetros
     */
    private function getParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Cerrar conexión
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
?>
