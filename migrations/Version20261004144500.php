<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lets a Slide be either an uploaded image (as before) or a big reminder
 * text, chosen via the new `type` column. `filename` becomes nullable since
 * a text slide has none.
 */
final class Version20261004144500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Slide.type/text, make Slide.filename nullable';
    }

    public function up(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql("CREATE TEMPORARY TABLE __temp__slide AS SELECT id, filename, caption, position, active, created_at FROM slide");
            $this->addSql('DROP TABLE slide');
            $this->addSql("CREATE TABLE slide (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(10) DEFAULT 'image' NOT NULL, filename VARCHAR(255) DEFAULT NULL, text CLOB DEFAULT NULL, caption VARCHAR(255) DEFAULT NULL, position INTEGER NOT NULL, active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL)");
            $this->addSql("INSERT INTO slide (id, filename, caption, position, active, created_at) SELECT id, filename, caption, position, active, created_at FROM __temp__slide");
            $this->addSql('DROP TABLE __temp__slide');

            return;
        }

        $this->addSql("ALTER TABLE slide ADD type VARCHAR(10) DEFAULT 'image' NOT NULL, ADD text LONGTEXT DEFAULT NULL, MODIFY filename VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $isSqlite = $this->connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $this->addSql("DELETE FROM slide WHERE type = 'text'");
            $this->addSql("CREATE TEMPORARY TABLE __temp__slide AS SELECT id, filename, caption, position, active, created_at FROM slide");
            $this->addSql('DROP TABLE slide');
            $this->addSql("CREATE TABLE slide (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, caption VARCHAR(255) DEFAULT NULL, position INTEGER NOT NULL, active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL)");
            $this->addSql("INSERT INTO slide (id, filename, caption, position, active, created_at) SELECT id, filename, caption, position, active, created_at FROM __temp__slide");
            $this->addSql('DROP TABLE __temp__slide');

            return;
        }

        $this->addSql("DELETE FROM slide WHERE type = 'text'");
        $this->addSql('ALTER TABLE slide DROP COLUMN type, DROP COLUMN text, MODIFY filename VARCHAR(255) NOT NULL');
    }
}
