<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replaces the single on/off schedule on Setting with a list of
 * screen_off_period rows, so several off-windows can coexist.
 *
 * Raw SQL, same reasoning as Version20261004122001: schema introspection
 * via getTable() is avoided here.
 */
final class Version20261004131900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add screen_off_period table, drop Setting's single on/off schedule";
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql('CREATE TABLE screen_off_period (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_time VARCHAR(5) NOT NULL, end_time VARCHAR(5) NOT NULL, enabled BOOLEAN DEFAULT 1 NOT NULL)');

            $this->addSql('CREATE TEMPORARY TABLE __temp__setting AS SELECT id, override_preset_id, override_until FROM setting');
            $this->addSql('DROP TABLE setting');
            $this->addSql('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, override_preset_id INTEGER DEFAULT NULL, override_until DATETIME DEFAULT NULL, CONSTRAINT fk_setting_override_preset FOREIGN KEY (override_preset_id) REFERENCES preset (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('INSERT INTO setting (id, override_preset_id, override_until) SELECT id, override_preset_id, override_until FROM __temp__setting');
            $this->addSql('DROP TABLE __temp__setting');
            $this->addSql('CREATE INDEX IDX_9F74B898FE236CF5 ON setting (override_preset_id)');

            return;
        }

        // MySQL / MariaDB
        $this->addSql('CREATE TABLE screen_off_period (id INT AUTO_INCREMENT NOT NULL, start_time VARCHAR(5) NOT NULL, end_time VARCHAR(5) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE setting DROP COLUMN schedule_enabled, DROP COLUMN screen_on_time, DROP COLUMN screen_off_time');
    }

    public function down(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        $this->addSql('DROP TABLE screen_off_period');

        if ($isSqlite) {
            $this->addSql('CREATE TEMPORARY TABLE __temp__setting AS SELECT id, override_preset_id, override_until FROM setting');
            $this->addSql('DROP TABLE setting');
            $this->addSql("CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, schedule_enabled BOOLEAN DEFAULT 0 NOT NULL, screen_on_time VARCHAR(5) DEFAULT '07:00' NOT NULL, screen_off_time VARCHAR(5) DEFAULT '22:00' NOT NULL, override_preset_id INTEGER DEFAULT NULL, override_until DATETIME DEFAULT NULL, CONSTRAINT fk_setting_override_preset FOREIGN KEY (override_preset_id) REFERENCES preset (id) NOT DEFERRABLE INITIALLY IMMEDIATE)");
            $this->addSql('INSERT INTO setting (id, override_preset_id, override_until) SELECT id, override_preset_id, override_until FROM __temp__setting');
            $this->addSql('DROP TABLE __temp__setting');
            $this->addSql('CREATE INDEX IDX_9F74B898FE236CF5 ON setting (override_preset_id)');

            return;
        }

        $this->addSql("ALTER TABLE setting ADD schedule_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD screen_on_time VARCHAR(5) DEFAULT '07:00' NOT NULL, ADD screen_off_time VARCHAR(5) DEFAULT '22:00' NOT NULL");
    }
}
