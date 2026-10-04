<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Allows several destinations per calendar day (e.g. École in the morning,
 * Travail in the afternoon): the unique index moves from (date) alone to
 * (date, preset_id).
 */
final class Version20261004150900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple CalendarDay rows per date';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql('DROP INDEX uniq_calendar_day_date');
            $this->addSql('CREATE UNIQUE INDEX uniq_calendar_day_date_preset ON calendar_day (date, preset_id)');

            return;
        }

        $this->addSql('ALTER TABLE calendar_day DROP INDEX uniq_calendar_day_date, ADD UNIQUE INDEX uniq_calendar_day_date_preset (date, preset_id)');
    }

    public function down(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql('DROP INDEX uniq_calendar_day_date_preset');
            $this->addSql('CREATE UNIQUE INDEX uniq_calendar_day_date ON calendar_day (date)');

            return;
        }

        $this->addSql('ALTER TABLE calendar_day DROP INDEX uniq_calendar_day_date_preset, ADD UNIQUE INDEX uniq_calendar_day_date (date)');
    }
}
