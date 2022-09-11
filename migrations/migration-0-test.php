<?php

return new class extends BasicPHPMigrator\Migration {

    public function up(): bool
    {

        $this->db->query('CREATE TABLE IF NOT EXISTS `test` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
        return true;
    }

    public function down(): bool
    {
        $this->db->query('DROP TABLE IF EXISTS `test`');

        return true;
    }
};