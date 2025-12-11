<?php
/**
 * JobNexus - Database Connection Class
 * Singleton PDO Connection
 */

class Database
{
  private static ?Database $instance = null;
  private PDO $connection;

  private function __construct()
  {
    try {
      $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . "; SET time_zone = '+05:00'"
      ];

      $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

    } catch (PDOException $e) {
      error_log("Database Connection Error: " . $e->getMessage());
      throw new Exception("Database connection failed. Please try again later.");
    }
  }

  public static function getInstance(): Database
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function getConnection(): PDO
  {
    return $this->connection;
  }

  // Prevent cloning
  private function __clone()
  {
  }

  // Prevent unserialization
  public function __wakeup()
  {
    throw new Exception("Cannot unserialize singleton");
  }
}

/**
 * Get Database Connection
 */
function db(): PDO
{
  return Database::getInstance()->getConnection();
}
