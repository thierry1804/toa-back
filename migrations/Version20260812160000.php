<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing site_id column/FK on site_prevention (SitePrevention::$site)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE site_prevention
                ADD COLUMN IF NOT EXISTS site_id UUID DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE site_prevention
                DROP CONSTRAINT IF EXISTS fk_site_prevention_site
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE site_prevention
                ADD CONSTRAINT fk_site_prevention_site
                FOREIGN KEY (site_id) REFERENCES site(id) ON DELETE SET NULL
                NOT VALID
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE site_prevention
                DROP CONSTRAINT IF EXISTS fk_site_prevention_site
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE site_prevention
                DROP COLUMN IF EXISTS site_id
        SQL);
    }
}
