<?php
require_once 'config.php';
require_once 'logger.php';

class db_context {
    private $connection;
    private $stmt;
    function open() {
        $this->connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
        $this->stmt = null;
        if ($this->connection->connect_errno) {
            throw new Exception('MySQL connection error: ' . $this->connection->connect_error);
        }
        // $this->connection->set_charset("utf8");
        return $this;
    }
    function close() {
        mysqli_close($this->connection);
        $this->connection = null;
        $this->close_stmt();
    }
    function close_stmt() {
        if ($this->stmt == null) return;
        mysqli_stmt_close($this->stmt);
        $this->stmt = null;
    }
    function check_error() {
        if ($this->connection->errno) {
            throw new Exception('MySQL error: ' . $this->connection->error);
        }
    }
    function prepare($sql, $param_types, ...$params) {
        $connection = $this->connection;
        $this->stmt = null;
        $stmt = $connection->prepare($sql);
        if(!$stmt) {
            throw new Exception('Wrong SQL: ' . $sql . ' Error: ' . $connection->errno . ' ' . $connection->error);
        }
        $this->check_error();
        $stmt->bind_param($param_types, ...$params);
        $this->stmt = $stmt;
        return $this;
    }
    function execute() {
        $stmt = $this->stmt;
        if (!$stmt) {
            throw new Exception("Can't execute invalid statement");
        } else if (!$stmt->execute()) {
            throw new Exception('Statement execution failed: ' . $stmt->error);
        }
        $this->check_error();
        return $this;
    }
    function get_result() {
        $stmt = $this->stmt;
        if (!$stmt) {
            throw new Exception("Can't execute invalid statement");
        } else if (!$result = $stmt->get_result()) {
            throw new Exception("Error getting result");
        }
        $this->check_error();
        $this->close_stmt();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}