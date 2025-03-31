<?php
require_once 'config.php';
require_once 'logger.php';

class db_context {
    private $connection;
    function connect() {
        try {
            $this->connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
            if ($this->connection->connect_errno) {
                log_error('MySQL connection error: ' . $this->connection->connect_error);
                return null;
            }
            // $this->connection->set_charset("utf8");
            return $this->connection;
        } catch (Exception $e) {
            log_error('MySQL connection exception: ' . $e->getMessage());
            return null;
        }
    }
    function disconnect() {
        mysqli_close($this->connection);
    }
    function check_error() {
        if ($this->connection->errno) {
            log_error('MySQL error: ' . $this->connection->error);
            return true;
        } else {
            return false;
        }
    }
    function query($sql, $param_types, ...$params) {
        try {
            $connection = $this->connection;
            if ($params != null && $param_types != null) {
                $stmt = $this->prepare($sql);
                $stmt->bind_param($param_types, ...$params);
                $result = $this->execute($stmt, true);
            } else {
                $result = mysqli_query($connection, $sql);
            }
            if ($this->check_error()) return null;
            else if ($result != null) {
                return $this->fetch($result);
            } else {
                return True;
            }
        } catch (Exception $e) {
            log_error("MySQL exception: " . $e->getMessage());
            return null;
        }
    }
    function prepare($sql) {
        $connection = $this->connection;
        $stmt = $connection->prepare($sql);
        if($stmt === false) {
            log_error('Wrong SQL: ' . $sql . ' Error: ' . $connection->errno . ' ' . $connection->error, E_USER_ERROR);
        }
        return $stmt;
    }
    function execute($stmt, $getResult = false) {
        if(!$stmt->execute()) {
            log_error($stmt->error);
        }
        if($getResult) {
            if($result = $stmt->get_result()) {
                $stmt->close();
                return $result;
            } else {
                log_error($stmt->error);
            }
        }
        $stmt->close();
        return null;
    }
    function fetch($result) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}