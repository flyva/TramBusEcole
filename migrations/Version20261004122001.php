<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the calendar_day table (one Preset per specific date) and drops the
 * now-unused Preset.daysOfWeek column. Written with the portable Schema API
 * so it works on SQLite and MySQL.
 */
final class Version20261004122001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar_day table, drop preset.days_of_week';
    }

    public function up(Schema $schema): void
    {
        $preset = $schema->getTable('preset');
        $preset->dropColumn('days_of_week');

        $calendarDay = $schema->createTable('calendar_day');
        $calendarDay->addColumn('id', 'integer', ['autoincrement' => true]);
        $calendarDay->addColumn('date', 'date_immutable');
        $calendarDay->addColumn('preset_id', 'integer');
        $calendarDay->setPrimaryKey(['id']);
        $calendarDay->addUniqueIndex(['date'], 'uniq_calendar_day_date');
        $calendarDay->addIndex(['preset_id'], 'IDX_1BC14B2380688E6F');
        $calendarDay->addForeignKeyConstraint($preset->getName(), ['preset_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_calendar_day_preset');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('calendar_day');

        $preset = $schema->getTable('preset');
        $preset->addColumn('days_of_week', 'json');
    }
}
