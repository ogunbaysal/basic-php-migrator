<?php

namespace BasicPHPMigrator;

use PDO;

/**
 * DB Interface to Help running migration sql commands. You can use any db adapter you want. You just need to implement the methods in this class.
 */
interface DBInterface
{
    /**
     * The method to run sql commands.
     * @param string $sql
     * @return bool
     */
    public function query(string $sql): bool;

    /**
     * The method to begin a transaction.
     * @return void
     */
    public function beginTransaction(): void;

    /**
     * The method to commit a transaction.
     * @return void
     */
    public function commit(): void;

    /**
     * The method to roll back a transaction.
     * @return void
     */
    public function rollback(): void;
}

/**
 * Abstract class which all migrators should extend.
 */
abstract class Migration
{
    /**
     * Stores the last error.
     * @var string|null
     */
    private ?string $lastError = null;
    /**
     * DB Instance
     * @var DBInterface
     */
    protected DBInterface $db;

    /**
     * Migrates db up.
     * @param DBInterface $db
     * @return bool
     */
    public function migrate(DBInterface $db): bool
    {
        $this->db = $db;
        try {
            return $this->up();
        } catch (\Exception $ex) {
            $this->lastError = "Error: " . $ex->getMessage();
            return false;
        }
    }

    /**
     * Migrates db down.
     * @param DBInterface $db
     * @return bool
     */
    public function revert(DBInterface $db): bool
    {
        $this->db = $db;
        try {
            return $this->down();
        } catch (\Exception $ex) {
            $this->lastError = "Error: " . $ex->getMessage();
            return false;
        }
    }

    /**
     * Returns the last error.
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return bool
     */
    public abstract function up(): bool;

    /**
     * @return bool
     */
    public abstract function down(): bool;
}

class mysqli_db implements DBInterface
{
    /**
     * @var \mysqli
     */
    private \mysqli $db;

    /**
     * mysqli_db constructor.
     * @param string $user
     * @param string $password
     * @param string $dbname
     * @param string $host
     * @param int $port
     */
    public function __construct(string $user, string $password, string $dbname, string $host = 'localhost', int $port = 3306)
    {
        $this->db = new \mysqli($host, $user, $password, $dbname, $port);
    }

    /**
     * @param string $sql
     * @return bool
     */
    public function query(string $sql): bool
    {
        return $this->db->query($sql);
    }

    /**
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->db->begin_transaction();
    }

    /**
     * @return void
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * @return void
     */
    public function rollback(): void
    {
        $this->db->rollback();
    }
}

/**
 * Actual Migration Class.
 */
class Migrator
{
    /**
     * The directory where migration files are located.
     * @var string|mixed
     */
    private string $migrations_directory;
    /**
     * The directory where version file is located.
     * @var string|mixed
     */
    private string $version_file_path;
    /**
     * Migration file prefix
     * @var string
     */
    private string $migrate_file_prefix = 'migration-';
    /**
     * Migration file suffix
     * @var string
     */
    private string $migrate_file_suffix = '.php';
    /**
     * DB Instance
     * @var DBInterface
     */
    private DBInterface $db;

    /**
     * Creates a new instance of Migrator.
     *
     * Default Migration Directory: __DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR
     * Default Version Directory: __DIR__ . '/.migrator_version'
     * @param DBInterface $db
     * @param $migrations_directory
     * @param $version_file_path
     */
    public function __construct(DBInterface $db, $migrations_directory = null, $version_file_path = null)
    {
        $this->db = $db;
        $this->migrations_directory = $migrations_directory ?? __DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR;
        $this->version_file_path = $version_file_path ?? __DIR__ . '/.migrator_version';
    }

    /**
     * Migrates the migrations. If $version is null, it will migrate all migrations. If $version is a number, it will migrate to that version.
     * @param int|null $target_version
     * @return void
     */
    public function up(?int $target_version = null)
    {
        $files = $this->getMigrationFiles();
        if (count($files) === 0) {
            echo 'No migration file is found.' . PHP_EOL;
            return;
        }
        $current_version = $this->getVersion();
        if ($target_version === null) {
            $target_version = count($files);
        }
        if ($target_version === $current_version) {
            echo 'Already at the latest version.' . PHP_EOL;
            return;
        }
        if ($target_version < $current_version) {
            echo "Target version $target_version is less than current version $current_version" . PHP_EOL;
            return;
        }
        if ($target_version > count($files)) {
            echo "Target version $target_version is greater than the latest version " . count($files) . PHP_EOL;
            return;
        }
        $this->db->beginTransaction();
        for ($i = $current_version; $i < $target_version; $i++) {
            $file = $files[$i];
            $file_path = $this->migrations_directory . $file;
            if (!file_exists($file_path)) {
                echo "File $file_path does not exist" . PHP_EOL;
                $this->db->rollback();
                return;
            }
            $migration_class = require $file_path;
            if (($migration_class instanceof Migration) === false) {
                echo "File $file does not return a Migration class" . PHP_EOL;
                $this->db->rollback();
                return;
            }
            $migration = new $migration_class();
            if (!$migration->migrate($this->db)) {
                echo "Migration $file failed: " . $migration->getLastError() . PHP_EOL;
                $this->db->rollback();
                return;
            }
            $this->setVersion($i + 1);
            echo "Migration $file succeeded" . PHP_EOL;
        }
        try {
            $this->db->commit();
        }
        catch (\Exception $exception) {
            echo "Commit failed: " . $exception->getMessage() . PHP_EOL;
            $this->db->rollback();
            return;
        }

        echo "Migration up to version $target_version succeeded" . PHP_EOL;
        echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL;
    }

    /**
     * Rolls back the migrations. If $version is null, it will roll back all migrations. If $version is a number, it will roll back to that version.
     * @param int|null $target_version
     * @return void
     */
    public function down(?int $target_version = null)
    {
        $current_version = $this->getVersion();
        if ($target_version === null) {
            $target_version = $current_version - 1;
        } else if (isset($target_version) && $target_version > $current_version) {
            echo "Target version $target_version is greater than current version $current_version" . PHP_EOL;
            return;
        } else if (isset($target_version) && $target_version < 0) {
            echo "Target version $target_version is less than 0" . PHP_EOL;
            return;
        }

        $files = $this->getMigrationFiles();
        if (count($files) === 0) {
            echo 'No migration file is found.' . PHP_EOL;
            return;
        }

        $this->db->beginTransaction();
        for ($i = $current_version - 1; $i >= $target_version; $i--) {
            $file = $files[$i];
            $file_path = $this->migrations_directory . $file;
            if (!file_exists($file_path)) {
                echo "File $file_path does not exist" . PHP_EOL;
                $this->db->rollback();
                return;
            }
            $migration_class = require $file_path;
            if (($migration_class instanceof Migration) === false) {
                echo "File $file does not return a Migration class" . PHP_EOL;
                $this->db->rollback();
                return;
            }
            $migration = new $migration_class();
            if (!$migration->revert($this->db)) {
                echo "Rollback $file failed: " . $migration->getLastError() . PHP_EOL;
                $this->db->rollback();
                return;
            }
            $this->setVersion($i);
            echo "Rollback $file succeeded" . PHP_EOL;
        }
        $this->db->commit();

        echo "Rollback to version $target_version succeeded" . PHP_EOL;
        echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL;
    }

    /**
     * Creates a new migration file with pattern to migration directory.
     * @param string $migration_name
     * @return bool
     */
    public function create(string $migration_name): bool
    {
        // check if directory exists. If directory is not exists, create directory
        if (!file_exists($this->migrations_directory)) {
            if (!mkdir($this->migrations_directory, 0755, true)) {
                echo "Cannot create directory $this->migrations_directory" . PHP_EOL;
                return false;
            }
        }
        $version = $this->getLastMigrationID() + 1;
        $file_name = $this->migrate_file_prefix . $version . '-' . $migration_name . $this->migrate_file_suffix;
        $file_path = $this->migrations_directory . $file_name;
        if (file_exists($file_path)) {
            echo "File $file_path already exists" . PHP_EOL;
            return false;
        }
        $content = <<<EOT
<?php

return new class extends BasicPHPMigrator\Migration {

    public function up(): bool
    {
        // TODO: Implement up() method.

        return true;
    }

    public function down(): bool
    {
        // TODO: Implement down() method.

        return true;
    }
};
EOT;
        file_put_contents($file_path, $content);
        echo "File $file_path created" . PHP_EOL;
        return true;
    }

    /**
     * Returns all migration files in the migration directory.
     * @return array
     */
    private function getMigrationFiles(): array
    {
        $files = [];
        $migration_files = scandir($this->migrations_directory);
        foreach ($migration_files as $file) {
            if (strpos($file, $this->migrate_file_prefix) === 0 && strpos($file, $this->migrate_file_suffix) === strlen($file) - strlen($this->migrate_file_suffix)) {
                $files[] = $file;
            }
        }
        asort($files);
        return $files;
    }

    /**
     * Returns the last migration ID in the migration directory.
     * @return int
     */
    private function getLastMigrationID(): int
    {
        $files = $files ?? $this->getMigrationFiles();
        if (empty($files)) {
            return -1;
        }
        $last_file = end($files);
        $last_file_name = substr($last_file, strlen($this->migrate_file_prefix), -strlen($this->migrate_file_suffix));
        return intval($last_file_name);
    }

    /**
     * Returns the current version of the database.
     * @return int
     */
    public function getVersion(): int
    {
        if (!file_exists($this->version_file_path)) {
            return 0;
        }
        $version = file_get_contents($this->version_file_path);
        if ($version === false) {
            return 0;
        }
        return (int)$version;
    }

    /**
     * Sets the current version of the database.
     * @param int $version
     * @return void
     */
    private function setVersion(int $version): void
    {
        file_put_contents($this->version_file_path, $version) !== false;
    }
}
