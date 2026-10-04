<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the preset table and the manual-override columns on setting.
 * Written with the portable Schema API so it works on SQLite and MySQL.
 */
final class Version20261004092400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preset table and setting.override_* columns';
    }

    public function up(Schema $schema): void
    {
        $preset = $schema->createTable('preset');
        $preset->addColumn('id', 'integer', ['autoincrement' => true]);
        $preset->addColumn('name', 'string', ['length' => 100]);
        $preset->addColumn('address', 'string', ['length' => 255]);
        $preset->addColumn('lat', 'float');
        $preset->addColumn('lng', 'float');
        $preset->addColumn('days_of_week', 'json');
        $preset->addColumn('start_time', 'string', ['length' => 5, 'notnull' => false]);
        $preset->addColumn('end_time', 'string', ['length' => 5, 'notnull' => false]);
        $preset->addColumn('enabled', 'boolean', ['default' => true]);
        $preset->addColumn('created_at', 'datetime_immutable');
        $preset->setPrimaryKey(['id']);

        $setting = $schema->getTable('setting');
        $setting->addColumn('override_until', 'datetime_immutable', ['notnull' => false]);
        $setting->addColumn('override_preset_id', 'integer', ['notnull' => false]);
        $setting->addForeignKeyConstraint($preset->getName(), ['override_preset_id'], ['id'], [], 'fk_setting_override_preset');
        $setting->addIndex(['override_preset_id'], 'IDX_9F74B898FE236CF5');
    }

    public function down(Schema $schema): void
    {
        $setting = $schema->getTable('setting');
        $setting->removeForeignKey('fk_setting_override_preset');
        $setting->dropColumn('override_preset_id');
        $setting->dropColumn('override_until');

        $schema->dropTable('preset');
    }
}
