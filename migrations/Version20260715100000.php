<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add qualite_representant and signature_path to user; seed user.upload_signature action_key and role_actions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD qualite_representant VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD signature_path VARCHAR(255) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO action_key (key, label, module, ownership_field)
            VALUES ('user.upload_signature', 'Uploader sa signature électronique', 'user', 'id')
            ON CONFLICT (key) DO NOTHING
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO role_action (role_name, action_key, bypass_ownership)
            VALUES
                ('ROLE_PRESTATAIRE', 'user.upload_signature', false),
                ('ROLE_ADMIN',       'user.upload_signature', true),
                ('ROLE_SUPER_ADMIN', 'user.upload_signature', true)
            ON CONFLICT (role_name, action_key) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN qualite_representant');
        $this->addSql('ALTER TABLE "user" DROP COLUMN signature_path');

        $this->addSql("DELETE FROM role_action WHERE action_key = 'user.upload_signature'");
        $this->addSql("DELETE FROM action_key WHERE key = 'user.upload_signature'");
    }
}
