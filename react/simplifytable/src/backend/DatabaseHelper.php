<?php
/**
 * =============================================================================
 * DatabaseHelper — MSSQL / MySQL SQL Abstraction
 * =============================================================================
 *
 * Centralizes all database-dialect-specific SQL generation so that query.php,
 * init.php, and savepreferences.php can work with both MSSQL and MySQL without
 * scattering conditional logic everywhere.
 *
 * Usage:
 *   require_once __DIR__ . '/DatabaseHelper.php';
 *   $dbHelper = new DatabaseHelper($jobDB, $CONFIG['DB_TYPE']);
 */

class DatabaseHelper
  {
  private string $dbType;
  private $jobDB;

  /**
   * @param mixed  $jobDB  The JobRouter database connection object
   * @param string $configDbType  'mssql', 'mysql', or 'auto'
   */
  public function __construct($jobDB, string $configDbType = 'auto')
    {
    $this->jobDB = $jobDB;
    $this->dbType = $configDbType === 'auto'
      ? $this->detectDbType()
      : strtolower($configDbType);
    }

  /**
   * Get the resolved database type ('mssql' or 'mysql').
   */
  public function getDbType(): string
    {
    return $this->dbType;
    }

  /**
   * Is this an MSSQL database?
   */
  public function isMssql(): bool
    {
    return $this->dbType === 'mssql';
    }

  /**
   * Is this a MySQL database?
   */
  public function isMysql(): bool
    {
    return $this->dbType === 'mysql';
    }

  // =========================================================================
  // DETECTION
  // =========================================================================

  /**
   * Auto-detect database type by querying version info.
   */
  private function detectDbType(): string
    {
    try {
      $result = $this->jobDB->query("SELECT @@VERSION");
      if ($result) {
        return 'mssql';
        }
      } catch (\Exception $e) {
      // Not MSSQL
      }

    try {
      $result = $this->jobDB->query("SELECT VERSION()");
      if ($result) {
        return 'mysql';
        }
      } catch (\Exception $e) {
      // Not MySQL
      }

    throw new \Exception("Database type could not be detected");
    }

  // =========================================================================
  // PAGINATION
  // =========================================================================

  /**
   * Append pagination to a query string.
   *
   * @param string $query     Base query (with ORDER BY already included)
   * @param int    $offset    Offset (0-based)
   * @param int    $limit     Number of rows to fetch
   * @return string           Query with pagination appended
   */
  public function paginateQuery(string $query, int $offset, int $limit): string
    {
    if ($this->isMssql()) {
      return $query . " OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
      }
    return $query . " LIMIT {$limit} OFFSET {$offset}";
    }

  // =========================================================================
  // DATE FUNCTIONS
  // =========================================================================

  /**
   * SQL expression for the current date/time.
   */
  public function currentDate(): string
    {
    return $this->isMssql() ? 'GETDATE()' : 'NOW()';
    }

  /**
   * SQL expression for current date only (no time).
   */
  public function currentDateOnly(): string
    {
    return $this->isMssql()
      ? 'CAST(GETDATE() AS date)'
      : 'CURDATE()';
    }

  /**
   * SQL expression: number of days between a column and now.
   *
   * @param string $column  Column name containing a date
   * @return string         SQL expression evaluating to integer number of days
   */
  public function dateDiffDays(string $column): string
    {
    return $this->isMssql()
      ? "DATEDIFF(day, {$column}, GETDATE())"
      : "DATEDIFF(NOW(), {$column})";
    }

  /**
   * SQL expression: safely convert a column to date type.
   *
   * @param string $column  Column name
   * @return string         SQL expression
   */
  public function tryConvertDate(string $column): string
    {
    return $this->isMssql()
      ? "TRY_CONVERT(date, {$column})"
      : "DATE({$column})";
    }

  // =========================================================================
  // TABLE MANAGEMENT
  // =========================================================================

  /**
   * Check whether a table exists in the database.
   *
   * @param string $tableName
   * @return bool
   */
  public function tableExists(string $tableName): bool
    {
    try {
      if ($this->isMysql()) {
        $query = "SHOW TABLES LIKE '{$tableName}'";
        $result = $this->jobDB->query($query);
        $row = $this->jobDB->fetchRow($result);
        return !empty($row);
        }

      $query = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '{$tableName}'";
      $result = $this->jobDB->query($query);
      $row = $this->jobDB->fetchRow($result);
      return !empty($row);
      } catch (\Exception $e) {
      return false;
      }
    }

  /**
   * Generate CREATE TABLE SQL for the user preferences table.
   *
   * @param string $tableName
   * @return string  CREATE TABLE SQL statement
   */
  public function createPreferencesTableSQL(string $tableName): string
    {
    if ($this->isMysql()) {
      return "
                CREATE TABLE {$tableName} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL,
                    filter TEXT,
                    column_order TEXT,
                    sort_column VARCHAR(100),
                    sort_direction VARCHAR(4),
                    current_page INT DEFAULT 1,
                    entries_per_page INT DEFAULT 25,
                    zoom_level FLOAT DEFAULT 1.0,
                    visible_columns TEXT,
                    visible_filters TEXT,
                    filter_presets TEXT,
                    theme_mode VARCHAR(10) DEFAULT 'dark',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user (username)
                )
            ";
      }

    return "
            CREATE TABLE {$tableName} (
                id INT IDENTITY(1,1) PRIMARY KEY,
                username NVARCHAR(255) NOT NULL,
                filter NVARCHAR(MAX),
                column_order NVARCHAR(MAX),
                sort_column NVARCHAR(100),
                sort_direction NVARCHAR(4),
                current_page INT DEFAULT 1,
                entries_per_page INT DEFAULT 25,
                zoom_level FLOAT DEFAULT 1.0,
                visible_columns NVARCHAR(MAX),
                visible_filters NVARCHAR(MAX),
                filter_presets NVARCHAR(MAX),
                theme_mode NVARCHAR(10) DEFAULT 'dark',
                updated_at DATETIME DEFAULT GETDATE(),
                CONSTRAINT unique_user UNIQUE (username)
            )
        ";
    }

  /**
   * SQL expression for the updated_at value in an UPDATE statement.
   */
  public function updatedAtExpression(): string
    {
    return $this->isMysql() ? 'CURRENT_TIMESTAMP' : 'GETDATE()';
    }

  // =========================================================================
  // STRING FUNCTIONS
  // =========================================================================

  /**
   * SQL CONCAT expression.
   *
   * @param string ...$parts  SQL expressions to concatenate
   * @return string
   */
  public function concat(string ...$parts): string
    {
    return 'CONCAT(' . implode(', ', $parts) . ')';
    }

  // =========================================================================
  // VALUE HELPERS
  // =========================================================================

  /**
   * Escape a value for use in LIKE patterns.
   * Escapes %, _, and [ characters.
   *
   * @param string $value
   * @return string
   */
  public function escapeLike(string $value): string
    {
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace('%', '\\%', $value);
    $value = str_replace('_', '\\_', $value);
    if ($this->isMssql()) {
      $value = str_replace('[', '\\[', $value);
      }
    return $value;
    }

  /**
   * Safely quote a string value for SQL.
   * Uses addslashes as the JobRouter DB layer does not provide prepared statements.
   *
   * @param string $value
   * @return string  Quoted string (with surrounding quotes)
   */
  public function quote(string $value): string
    {
    return "'" . addslashes($value) . "'";
    }

  /**
   * Generate a NULL-or-quoted value for SQL.
   *
   * @param string|null $value
   * @return string  'value' or NULL
   */
  public function quoteOrNull(?string $value): string
    {
    return $value !== null && $value !== '' ? $this->quote($value) : 'NULL';
    }
  }
