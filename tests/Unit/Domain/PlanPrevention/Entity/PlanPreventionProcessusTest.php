<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\PlanPrevention\Entity;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\Referentiel\ProcessusReferentiel;
use App\Domain\User\Entity\User;
use PHPUnit\Framework\TestCase;

/** R-30/R-32 : rattachement des dossiers à un processus plutôt qu'à un chef de projet nominatif. */
class PlanPreventionProcessusTest extends TestCase
{
    public function testTitulaireDuProcessusGereLeDossier(): void
    {
        $plan = (new PlanPrevention())->setTypeIntervention(ProcessusReferentiel::ENERGIE);
        $titulaire = $this->chefProjet('a@toa.local', [ProcessusReferentiel::ENERGIE, ProcessusReferentiel::INFRASTRUCTURE]);

        $this->assertTrue($plan->estGereePar($titulaire));
    }

    public function testChefDUnAutreProcessusNeVoitPasLeDossierMemeSiDesigneNominativement(): void
    {
        $autre = $this->chefProjet('b@toa.local', [ProcessusReferentiel::NOUVEAU_SITE]);
        $plan = (new PlanPrevention())->setTypeIntervention(ProcessusReferentiel::ENERGIE)->setChefProjet($autre);

        $this->assertFalse($plan->estGereePar($autre));
    }

    public function testDeuxTitulairesDuMemeProcessusOntLesMemesDroits(): void
    {
        $plan = (new PlanPrevention())->setTypeIntervention(ProcessusReferentiel::VIE_DE_RESEAU);

        $this->assertTrue($plan->estGereePar($this->chefProjet('a@toa.local', [ProcessusReferentiel::VIE_DE_RESEAU])));
        $this->assertTrue($plan->estGereePar($this->chefProjet('b@toa.local', [ProcessusReferentiel::VIE_DE_RESEAU])));
    }

    public function testSansProcessusLaRegleNominativeSAppliqueEncore(): void
    {
        $nominatif = $this->chefProjet('c@toa.local', []);
        $plan = (new PlanPrevention())->setTypeIntervention(ProcessusReferentiel::ENERGIE)->setChefProjet($nominatif);

        $this->assertTrue($plan->estGereePar($nominatif));
        $this->assertFalse($plan->estGereePar($this->chefProjet('d@toa.local', [])));
    }

    /** @param list<string> $processus */
    private function chefProjet(string $email, array $processus): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test');
        $user->setFirstname('CP');
        $user->setRoles(['ROLE_CHEF_PROJET']);
        $user->setProcessus($processus);

        return $user;
    }
}
