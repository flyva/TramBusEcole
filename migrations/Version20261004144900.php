<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004144900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Setting.jingleRequestedAt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE setting ADD jingle_requested_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE setting DROP COLUMN jingle_requested_at');
    }
}
