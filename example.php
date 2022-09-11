<?php
require_once 'BasicPHPMigrator.php';

/*
 * This is a sample migrator for a project.
 * You can use this as a template for your own project.
 */


$db = new \BasicPHPMigrator\mysqli_db('DBUSERNAME', 'DBPASS', 'DBNAME');
$migrator = new BasicPHPMigrator\Migrator($db);


$command = $argv[1] ?? 'help';

if ($command === 'up') {
    $target = $argv[2] ?? null;
    $migrator->up($target);
}
else if ($command === 'down') {
    $target = $argv[2] ?? null;
    $migrator->down($target);
}
else if ($command === 'create') {
    $name = $argv[2] ?? null;
    if (empty($name)) {
        echo "Please enter a migration name.\n";
        echo "Usage: php migrator.php create <name>\n";
        return;
    }
    $migrator->create($name);
}
else if ($command === 'version') {
    $version = $migrator->getVersion();
    echo "Current version: $version" . PHP_EOL;
}
else {
    echo "Usage: php migrator.php [up|down|create|version] [target_version|migration_name]" . PHP_EOL;
}