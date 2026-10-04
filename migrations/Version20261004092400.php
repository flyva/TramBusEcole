<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004092400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE preset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, address VARCHAR(255) NOT NULL, lat DOUBLE PRECISION NOT NULL, lng DOUBLE PRECISION NOT NULL, days_of_week CLOB NOT NULL, start_time VARCHAR(5) DEFAULT NULL, end_time VARCHAR(5) DEFAULT NULL, enabled BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__setting AS SELECT id, schedule_enabled, screen_on_time, screen_off_time FROM setting');
        $this->addSql('DROP TABLE setting');
        $this->addSql('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, schedule_enabled BOOLEAN DEFAULT 0 NOT NULL, screen_on_time VARCHAR(5) DEFAULT \'07:00\' NOT NULL, screen_off_time VARCHAR(5) DEFAULT \'22:00\' NOT NULL, override_until DATETIME DEFAULT NULL, override_preset_id INTEGER DEFAULT NULL, CONSTRAINT FK_9F74B898FE236CF5 FOREIGN KEY (override_preset_id) REFERENCES preset (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO setting (id, schedule_enabled, screen_on_time, screen_off_time) SELECT id, schedule_enabled, screen_on_time, screen_off_time FROM __temp__setting');
        $this->addSql('DROP TABLE __temp__setting');
        $this->addSql('CREATE INDEX IDX_9F74B898FE236CF5 ON setting (override_preset_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE preset');
        $this->addSql('CREATE TEMPORARY TABLE __temp__setting AS SELECT id, schedule_enabled, screen_on_time, screen_off_time FROM setting');
        $this->addSql('DROP TABLE setting');
        $this->addSql('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, schedule_enabled BOOLEAN DEFAULT 0 NOT NULL, screen_on_time VARCHAR(5) DEFAULT \'07:00\' NOT NULL, screen_off_time VARCHAR(5) DEFAULT \'22:00\' NOT NULL)');
        $this->addSql('INSERT INTO setting (id, schedule_enabled, screen_on_time, screen_off_time) SELECT id, schedule_enabled, screen_on_time, screen_off_time FROM __temp__setting');
        $this->addSql('DROP TABLE __temp__setting');
    }
}
