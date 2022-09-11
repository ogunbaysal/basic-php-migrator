# Basic PHP Migrator

This is a basic PHP migrator that can be used to create migrations and migrate them.

## Getting Started

You can include the BasicPHPMigrator in your project and start using it. An example of how to use it is inside `example.php`.

### Usage

Before initializing migrator, you need to create a database connection. You can use any database connection library you want. The only requirement is that you need to create the class that implements `BasicPHPMigrator\DBInterface`. You can use the `BasicPHPMigrator\mysqli_db` class as an example.

BasicPHPMigrator class needs 1 parameter required and 2 parameter optional.
- DBInterface $db [Required]: Database connection class
- string $migrations_directory [Optional]: The relative or full path of the directory where the migrations are stored. Default is `__DIR__ . DIRECTORY_SEPARATOR . 'migrations'`
- string $version_file_path [Optional]: The relative or full path of the file where the current version is stored. Default is `__DIR__ . '/.migrator_version'`

```php
    <?php
    require_once 'BasicPHPMigrator.php';
    $db = new \BasicPHPMigrator\mysqli_db('DBUSER', 'DBPASS', 'DBNAME', 'DBHOST', 3306); 
    $migrator = new \BasicPHPMigrator\BasicPHPMigrator($db);
```


The methods you can use are:
- `create(string $name)` : Creates a new migration file with the given name. 
- `up(?int $target_version = null)` : Migrates the database to the given version. If no version is given, it will migrate to the latest version.
- `down(?int $target_version = null)` : Rolls back the database to the given version. If no version is given, it will roll back to the first version.
- `getVersion()` : Returns the current version of the database.

## Authors

- **Ogün Baysal**