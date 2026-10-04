<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the board's own origin address/coordinates to Setting, configurable
 * from the admin instead of the ORIGIN_LAT/ORIGIN_LNG constants that used
 * to live in DeparturesController. Defaults match those former constants so
 * existing deployments keep behaving the same until someone edits it.
 */
final class Version20261004141400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add Setting.origin{Address,Lat,Lng}";
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;

        if ($isSqlite) {
            $this->addSql("ALTER TABLE setting ADD COLUMN origin_address VARCHAR(255) DEFAULT '' NOT NULL");
            $this->addSql('ALTER TABLE setting ADD COLUMN origin_lat DOUBLE PRECISION DEFAULT 44.786049 NOT NULL');
            $this->addSql('ALTER TABLE setting ADD COLUMN origin_lng DOUBLE PRECISION DEFAULT -0.564053 NOT NULL');

            return;
        }

        $this->addSql("ALTER TABLE setting ADD origin_address VARCHAR(255) DEFAULT '' NOT NULL, ADD origin_lat DOUBLE PRECISION DEFAULT 44.786049 NOT NULL, ADD origin_lng DOUBLE PRECISION DEFAULT -0.564053 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;

        if ($isSqlite) {
            $this->addSql('ALTER TABLE setting DROP COLUMN origin_address');
            $this->addSql('ALTER TABLE setting DROP COLUMN origin_lat');
            $this->addSql('ALTER TABLE setting DROP COLUMN origin_lng');

            return;
        }

        $this->addSql('ALTER TABLE setting DROP COLUMN origin_address, DROP COLUMN origin_lat, DROP COLUMN origin_lng');
    }
}
