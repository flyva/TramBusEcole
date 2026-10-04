<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the two destinations every alternance day needs (École / Travail),
 * left disabled with no address/time window yet - just a starting point to
 * fill in via /admin/preset.
 */
final class Version20261004140500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed École and Travail presets';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(
            "INSERT INTO preset (name, address, lat, lng, start_time, end_time, enabled, created_at) VALUES ('École', '', 0, 0, NULL, NULL, 0, '$now')"
        );
        $this->addSql(
            "INSERT INTO preset (name, address, lat, lng, start_time, end_time, enabled, created_at) VALUES ('Travail', '', 0, 0, NULL, NULL, 0, '$now')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM preset WHERE name IN ('École', 'Travail') AND address = ''");
    }
}
