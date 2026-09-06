<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Processor;

use ApiPlatform\Metadata\Delete;
use App\Api\Processor\PermitTravailDeleteProcessor;
use App\Domain\Intervention\Entity\Intervention;
use App\Domain\Menu\Service\PermissionChecker;
use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Entity\PermitTravailGroupe;
use App\Domain\PermitTravail\Enum\ProcessusPermitTravail;
use App\Domain\PermitTravail\Enum\StatutPermitTravail;
use App\Domain\PermitTravail\Enum\TypeDocumentPermitTravail;
use App\Domain\PermitTravail\Enum\TypePermitTravail;
use App\Domain\PermitTravail\Repository\PermitTravailGroupeRepository;
use App\Domain\Role\Entity\RoleAction;
use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class PermitTravailDeleteProcessorTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private PermitTravailGroupeRepository $groupeRepository;
    private PermissionChecker $permissionChecker;
    private TokenStorageInterface $tokenStorage;
    private FilesystemOperator $storage;
    private PermitTravailDeleteProcessor $processor;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->groupeRepository = $this->createMock(PermitTravailGroupeRepository::class);
        $this->permissionChecker = $this->createMock(PermissionChecker::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->storage = $this->createMock(FilesystemOperator::class);

        $this->entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn(callable $fn) => $fn());

        $this->processor = new PermitTravailDeleteProcessor(
            $this->entityManager,
            $this->groupeRepository,
            $this->permissionChecker,
            $this->tokenStorage,
            $this->storage,
        );
    }

    public function testDeletesIsolatedBrouillonAndItsFiles(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::MAINTENANCE);
        $this->addDocument($permit, 'permits-travail/1/doc.pdf');
        $this->setActor($owner);
        $this->mockBypass(false);

        $this->entityManager->expects($this->once())->method('remove')->with($permit);
        $this->entityManager->expects($this->once())->method('flush');
        $this->storage->expects($this->once())->method('delete')->with('permits-travail/1/doc.pdf');

        $result = $this->processor->process($permit, new Delete());

        $this->assertNull($result);
    }

    public function testDeletesFullNouveauSiteGroupTogether(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $general = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::GENERAL);
        $specialise = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::ELECTRIQUE);
        $this->addDocument($general, 'permits-travail/general/doc.pdf');
        $this->addDocument($specialise, 'permits-travail/specialise/doc.pdf');

        $groupe = $this->buildGroupe($general, $specialise);
        $this->groupeRepository->method('findByCodeSiteAndPlan')->willReturn($groupe);

        $this->setActor($owner);
        $this->mockBypass(false);

        $removed = [];
        $this->entityManager->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function ($entity) use (&$removed): void {
                $removed[] = $entity;
            });
        $this->entityManager->expects($this->once())->method('flush');

        $deletedPaths = [];
        $this->storage->method('delete')->willReturnCallback(function (string $path) use (&$deletedPaths): void {
            $deletedPaths[] = $path;
        });

        $result = $this->processor->process($general, new Delete());

        $this->assertNull($result);
        $this->assertSame([$general, $specialise], $removed);
        $this->assertEqualsCanonicalizing(
            ['permits-travail/general/doc.pdf', 'permits-travail/specialise/doc.pdf'],
            $deletedPaths,
        );
    }

    public function testRefusesWhenCompanionIsNoLongerBrouillon(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $general = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::GENERAL);
        $specialise = $this->buildPermit($owner, StatutPermitTravail::SOUMIS, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::ELECTRIQUE);

        $groupe = $this->buildGroupe($general, $specialise);
        $this->groupeRepository->method('findByCodeSiteAndPlan')->willReturn($groupe);

        $this->setActor($owner);
        $this->mockBypass(false);

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');
        $this->storage->expects($this->never())->method('delete');

        $this->expectException(ConflictHttpException::class);
        $this->processor->process($general, new Delete());
    }

    public function testRefusesWhenCompanionBelongsToAnotherUser(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $someoneElse = $this->buildUser('someone-else@toa.local', 2);
        $general = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::GENERAL);
        $specialise = $this->buildPermit($someoneElse, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::ELECTRIQUE);

        $groupe = $this->buildGroupe($general, $specialise);
        $this->groupeRepository->method('findByCodeSiteAndPlan')->willReturn($groupe);

        $this->setActor($owner);
        $this->mockBypass(false);

        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');
        $this->storage->expects($this->never())->method('delete');

        $this->expectException(AccessDeniedException::class);
        $this->processor->process($general, new Delete());
    }

    public function testRefusesDeletionWhenPermitHasLinkedIntervention(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::MAINTENANCE);

        $intervention = new Intervention();
        $interventionIdProp = new \ReflectionProperty(Intervention::class, 'id');
        $interventionIdProp->setAccessible(true);
        $interventionIdProp->setValue($intervention, Uuid::v7());

        $permitInterventionProp = new \ReflectionProperty(PermitTravail::class, 'intervention');
        $permitInterventionProp->setAccessible(true);
        $permitInterventionProp->setValue($permit, $intervention);

        $this->setActor($owner);
        $this->mockBypass(false);

        $this->entityManager->expects($this->never())->method('remove');
        $this->storage->expects($this->never())->method('delete');

        $this->expectException(ConflictHttpException::class);
        $this->processor->process($permit, new Delete());
    }

    public function testNoPartialDeletionWhenFlushFails(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $permit = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::MAINTENANCE);
        $this->addDocument($permit, 'permits-travail/1/doc.pdf');
        $this->setActor($owner);
        $this->mockBypass(false);

        $this->entityManager->method('flush')->willThrowException(new \RuntimeException('db down'));
        $this->storage->expects($this->never())->method('delete');

        $this->expectException(\RuntimeException::class);
        $this->processor->process($permit, new Delete());
    }

    public function testBypassRoleCanDeleteAnotherUsersGroup(): void
    {
        $owner = $this->buildUser('owner@toa.local', 1);
        $hse = $this->buildUser('hse@toa.local', 2);
        $general = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::GENERAL);
        $specialise = $this->buildPermit($owner, StatutPermitTravail::BROUILLON, ProcessusPermitTravail::NOUVEAU_SITE, TypePermitTravail::ELECTRIQUE);

        $groupe = $this->buildGroupe($general, $specialise);
        $this->groupeRepository->method('findByCodeSiteAndPlan')->willReturn($groupe);

        $this->setActor($hse);
        $this->mockBypass(true);

        $this->entityManager->expects($this->exactly(2))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->processor->process($general, new Delete());

        $this->assertNull($result);
    }

    private function mockBypass(bool $bypass): void
    {
        $roleAction = $this->createMock(RoleAction::class);
        $roleAction->method('isBypassOwnership')->willReturn($bypass);
        $this->permissionChecker->method('getRoleActions')->willReturn([$roleAction]);
    }

    private function setActor(User $user): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $this->tokenStorage->method('getToken')->willReturn($token);
    }

    private function buildGroupe(PermitTravail $general, ?PermitTravail $specialise): PermitTravailGroupe
    {
        $groupe = new PermitTravailGroupe();
        $groupe->setCodeSite((string) $general->getCodeSite());
        $groupe->setCreatedBy($general->getCreatedBy());
        $groupe->setPermitGeneral($general);
        $groupe->setPermitSpecialise($specialise);

        return $groupe;
    }

    private function addDocument(PermitTravail $permit, string $filePath): void
    {
        $document = new PermitTravailDocument();
        $document->setType(TypeDocumentPermitTravail::TROUSSE_SECOURS);
        $document->setFilePath($filePath);
        $document->setMimeType('application/pdf');
        $document->setUploadedAt(new \DateTimeImmutable());
        $permit->addDocument($document);
    }

    private function buildPermit(
        User $createdBy,
        StatutPermitTravail $statut,
        ProcessusPermitTravail $processus,
        TypePermitTravail $type = TypePermitTravail::GENERAL,
    ): PermitTravail {
        $permit = new PermitTravail();
        $permit->setCodeSite('SITE-1');
        $permit->setType($type);
        $permit->setProcessus($processus);
        $permit->setStatut($statut);
        $permit->setCreatedBy($createdBy);

        $reflection = new \ReflectionProperty(PermitTravail::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($permit, Uuid::v7());

        return $permit;
    }

    private function buildUser(string $email, int $id): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test');
        $user->setFirstname('User');
        $user->setRoles(['ROLE_PRESTATAIRE']);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($user, $id);

        return $user;
    }
}
