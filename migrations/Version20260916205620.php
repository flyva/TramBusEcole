<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the setting, slide and messenger_messages tables. Written with
 * the portable Schema API (not raw SQL) so it works the same on SQLite and
 * MySQL, whichever the app is configured to use.
 */
final class Version20260916205620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create setting, slide and messenger_messages tables';
    }

    public function up(Schema $schema): void
    {
        $setting = $schema->createTable('setting');
        $setting->addColumn('id', 'integer', ['autoincrement' => true]);
        $setting->addColumn('schedule_enabled', 'boolean', ['default' => false]);
        $setting->addColumn('screen_on_time', 'string', ['length' => 5, 'default' => '07:00']);
        $setting->addColumn('screen_off_time', 'string', ['length' => 5, 'default' => '22:00']);
        $setting->setPrimaryKey(['id']);

        $slide = $schema->createTable('slide');
        $slide->addColumn('id', 'integer', ['autoincrement' => true]);
        $slide->addColumn('filename', 'string', ['length' => 255]);
        $slide->addColumn('caption', 'string', ['length' => 255, 'notnull' => false]);
        $slide->addColumn('position', 'integer');
        $slide->addColumn('active', 'boolean', ['default' => true]);
        $slide->addColumn('created_at', 'datetime_immutable');
        $slide->setPrimaryKey(['id']);

        $messenger = $schema->createTable('messenger_messages');
        $messenger->addColumn('id', 'integer', ['autoincrement' => true]);
        $messenger->addColumn('body', 'text');
        $messenger->addColumn('headers', 'text');
        $messenger->addColumn('queue_name', 'string', ['length' => 190]);
        $messenger->addColumn('created_at', 'datetime_immutable');
        $messenger->addColumn('available_at', 'datetime_immutable');
        $messenger->addColumn('delivered_at', 'datetime_immutable', ['notnull' => false]);
        $messenger->setPrimaryKey(['id']);
        $messenger->addIndex(['queue_name', 'available_at', 'delivered_at', 'id'], 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('messenger_messages');
        $schema->dropTable('slide');
        $schema->dropTable('setting');
    }
}
