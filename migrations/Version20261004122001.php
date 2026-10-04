<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the calendar_day table and drops the now-unused Preset.daysOfWeek
 * column.
 *
 * Written as raw SQL (not the portable Schema API used elsewhere) because
 * Doctrine's schema introspection - needed by getTable() to alter an
 * existing table - currently crashes against some MariaDB versions
 * (TableEditor::setOptions() receiving null). Raw addSql() skips that
 * introspection entirely. SQLite and MySQL/MariaDB syntax both handled.
 */
final class Version20261004122001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar_day table, drop preset.days_of_week';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql('CREATE TEMPORARY TABLE __temp__preset AS SELECT id, name, address, lat, lng, start_time, end_time, enabled, created_at FROM preset');
            $this->addSql('DROP TABLE preset');
            $this->addSql('CREATE TABLE preset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, address VARCHAR(255) NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, start_time VARCHAR(5) DEFAULT NULL, end_time VARCHAR(5) DEFAULT NULL, enabled BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL)');
            $this->addSql('INSERT INTO preset (id, name, address, lat, lng, start_time, end_time, enabled, created_at) SELECT id, name, address, lat, lng, start_time, end_time, enabled, created_at FROM __temp__preset');
            $this->addSql('DROP TABLE __temp__preset');
            $this->addSql('CREATE TABLE calendar_day (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, preset_id INTEGER NOT NULL, CONSTRAINT fk_calendar_day_preset FOREIGN KEY (preset_id) REFERENCES preset (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_calendar_day_date ON calendar_day (date)');
            $this->addSql('CREATE INDEX IDX_1BC14B2380688E6F ON calendar_day (preset_id)');

            return;
        }

        // MySQL / MariaDB
        $this->addSql('ALTER TABLE preset DROP COLUMN days_of_week');
        $this->addSql('CREATE TABLE calendar_day (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, preset_id INT NOT NULL, UNIQUE INDEX uniq_calendar_day_date (date), INDEX IDX_1BC14B2380688E6F (preset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE calendar_day ADD CONSTRAINT fk_calendar_day_preset FOREIGN KEY (preset_id) REFERENCES preset (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        $this->addSql('DROP TABLE calendar_day');

        if ($isSqlite) {
            $this->addSql('ALTER TABLE preset ADD COLUMN days_of_week CLOB NOT NULL DEFAULT \'[]\'');

            return;
        }

        $this->addSql('ALTER TABLE preset ADD days_of_week JSON NOT NULL');
    }
}
