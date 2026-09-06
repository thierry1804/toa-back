<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Offline\EventSubscriber;

use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\Offline\Entity\TombstoneRecord;
use App\Domain\Offline\EventSubscriber\TombstoneRecorderSubscriber;
use App\Domain\PermitTravail\Entity\PermitTravail;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class TombstoneRecorderSubscriberTest extends TestCase
{
    public function testTrackedEntityIsRecordedAsTombstoneOnPostFlush(): void
    {
        $permit = new PermitTravail();
        $this->setId($permit, '019ffb26-ebbb-7baa-9573-52e13a752d49');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TombstoneRecord $record): bool {
                return $record->getEntityType() === 'permit_travail'
                    && $record->getEntityId() === '019ffb26-ebbb-7baa-9573-52e13a752d49';
            }));
        $entityManager->expects($this->once())->method('flush');

        $subscriber = new TombstoneRecorderSubscriber();
        $subscriber->preRemove(new PreRemoveEventArgs($permit, $entityManager));
        $subscriber->postFlush(new PostFlushEventArgs($entityManager));
    }

    public function testUnrelatedEntityIsIgnored(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $subscriber = new TombstoneRecorderSubscriber();
        $subscriber->preRemove(new PreRemoveEventArgs(new \stdClass(), $entityManager));
        $subscriber->postFlush(new PostFlushEventArgs($entityManager));
    }

    public function testPostFlushWithoutPriorRemovalDoesNothing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        (new TombstoneRecorderSubscriber())->postFlush(new PostFlushEventArgs($entityManager));
    }

    public function testBufferIsClearedAfterPostFlushSoRepeatedFlushesDoNotReRecord(): void
    {
        $planning = new ActivityPlanning();
        $this->setIntId($planning, 42);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $subscriber = new TombstoneRecorderSubscriber();
        $subscriber->preRemove(new PreRemoveEventArgs($planning, $entityManager));
        $subscriber->postFlush(new PostFlushEventArgs($entityManager));

        // Un second postFlush sans nouvelle suppression ne doit rien réécrire.
        $subscriber->postFlush(new PostFlushEventArgs($entityManager));
    }

    private function setId(object $entity, string $uuid): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, Uuid::fromString($uuid));
    }

    private function setIntId(object $entity, int $id): void
    {
        $reflection = new \ReflectionProperty($entity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $id);
    }
}
