<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Referentiel\Service\DocumentPermisCatalogue;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiel des documents de permis, initialisé avec la liste actuelle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document_permis_regle (id UUID NOT NULL, code VARCHAR(100) NOT NULL, libelle VARCHAR(255) NOT NULL, moment VARCHAR(20) NOT NULL, processus VARCHAR(20) NOT NULL, type_permis VARCHAR(20) NOT NULL, demande VARCHAR(20) NOT NULL, apn_api_uniquement BOOLEAN DEFAULT false NOT NULL, obligatoire BOOLEAN DEFAULT true NOT NULL, ordre INT DEFAULT 0 NOT NULL, actif BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_document_permis_regle ON document_permis_regle (code, moment, processus, type_permis, demande, apn_api_uniquement)');

        foreach (DocumentPermisCatalogue::definitions() as $row) {
            $this->addSql(
                'INSERT INTO document_permis_regle (id, code, libelle, moment, processus, type_permis, demande, apn_api_uniquement, obligatoire, ordre, actif) VALUES (gen_random_uuid(), :code, :libelle, :moment, :processus, :type_permis, :demande, :apn, :obligatoire, :ordre, true)',
                [
                    'code' => $row['code'],
                    'libelle' => $row['libelle'],
                    'moment' => $row['moment'],
                    'processus' => $row['processus'],
                    'type_permis' => $row['typePermis'],
                    'demande' => $row['demande'],
                    'apn' => $row['apnApiUniquement'],
                    'obligatoire' => $row['obligatoire'],
                    'ordre' => $row['ordre'],
                ],
                [
                    'apn' => \Doctrine\DBAL\ParameterType::BOOLEAN,
                    'obligatoire' => \Doctrine\DBAL\ParameterType::BOOLEAN,
                    'ordre' => \Doctrine\DBAL\ParameterType::INTEGER,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_permis_regle');
    }
}
