<?php
/**
 * MN Order Panel - Database Configuration
 * اتصال به دیتابیس مجزای پنل (نه دیتابیس وردپرس)
 */

if (!defined('MN_PANEL_PATH')) {
    define('MN_PANEL_PATH', dirname(__DIR__));
}

class MN_Database {
    
    private static $instance = null;
    private $connection;
    
    // تنظیمات دیتابیس مجزای پنل
     private $config = [
        'host' => 'localhost',
        'dbname' => 'dbname',           // نام دیتابیس مجزا
        'user' => 'root',                     // کاربر دیتابیس
        'pass' => '',                         // رمز عبور
        'charset' => 'utf8mb4'
    ];
    /**
     * Singleton Pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - اتصال به دیتابیس
     */
    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['dbname'],
                $this->config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
                ]
            );
            
        } catch (PDOException $e) {
            die('Database Connection Failed: ' . $e->getMessage());
        }
    }
    
    /**
     * دریافت یک رکورد
     * 
     * @param string $query کوئری SQL
     * @param array $params پارامترها
     * @return object|null
     */
    public function get_row($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('DB Error (get_row): ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * دریافت چند رکورد
     * 
     * @param string $query کوئری SQL
     * @param array $params پارامترها
     * @return array
     */
    public function get_results($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('DB Error (get_results): ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت یک مقدار
     * 
     * @param string $query کوئری SQL
     * @param array $params پارامترها
     * @return mixed
     */
    public function get_var($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('DB Error (get_var): ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * درج رکورد جدید
     * 
     * @param string $table نام جدول
     * @param array $data آرایه associative از داده‌ها
     * @return int|false آخرین ID درج شده یا false
     */
    public function insert($table, $data) {
        try {
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            
            $query = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $fields),
                implode(', ', $placeholders)
            );
            
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_values($data));
            
            return $this->connection->lastInsertId();
            
        } catch (PDOException $e) {
            error_log('DB Error (insert): ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * بروزرسانی رکورد
     * 
     * @param string $table نام جدول
     * @param array $data آرایه associative از داده‌ها
     * @param array $where شرایط WHERE
     * @return bool
     */
    public function update($table, $data, $where) {
        try {
            $set_parts = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                $set_parts[] = "$field = ?";
                $values[] = $value;
            }
            
            $where_parts = [];
            foreach ($where as $field => $value) {
                $where_parts[] = "$field = ?";
                $values[] = $value;
            }
            
            $query = sprintf(
                'UPDATE %s SET %s WHERE %s',
                $table,
                implode(', ', $set_parts),
                implode(' AND ', $where_parts)
            );
            
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($values);
            
        } catch (PDOException $e) {
            error_log('DB Error (update): ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف رکورد
     * 
     * @param string $table نام جدول
     * @param array $where شرایط WHERE
     * @return bool
     */
    public function delete($table, $where) {
        try {
            $where_parts = [];
            $values = [];
            
            foreach ($where as $field => $value) {
                $where_parts[] = "$field = ?";
                $values[] = $value;
            }
            
            $query = sprintf(
                'DELETE FROM %s WHERE %s',
                $table,
                implode(' AND ', $where_parts)
            );
            
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($values);
            
        } catch (PDOException $e) {
            error_log('DB Error (delete): ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * اجرای کوئری دلخواه
     * 
     * @param string $query کوئری SQL
     * @param array $params پارامترها
     * @return bool
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('DB Error (query): ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * شروع Transaction
     */
    public function begin_transaction() {
        if ($this->connection->inTransaction()) {
            return true; // قبلاً شروع شده
        }
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit Transaction
     */
    public function commit() {
        if (!$this->connection->inTransaction()) {
            return true;
        }
        return $this->connection->commit();
    }
    
    /**
     * Rollback Transaction
     */
    public function rollback() {
        if (!$this->connection->inTransaction()) {
            return true;
        }
        return $this->connection->rollBack();
    }
    
    /**
     * Escape برای LIKE queries
     */
    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }
    
    /**
     * تبدیل آرایه به placeholders
     */
    public function prepare_in($values) {
        return implode(',', array_fill(0, count($values), '?'));
    }
}