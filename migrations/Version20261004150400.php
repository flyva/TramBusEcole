<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops the jingle feature's column: the board's screen (a plain monitor,
 * no built-in speakers) can't play sound at all, so it's not worth keeping.
 */
final class Version20261004150400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop Setting.jingleRequestedAt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE setting DROP COLUMN jingle_requested_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE setting ADD jingle_requested_at DATETIME DEFAULT NULL');
    }
}
