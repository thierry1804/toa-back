<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\ActivityPlanning\Entity\ActivityPlanning;
use App\Domain\ActivityPlanning\Message\ActivityPlanningCreatedNotification;
use App\Domain\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ActivityPlanningCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ActivityPlanning) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new UnprocessableEntityHttpException('user_not_authenticated');
        }

        $data->setCreatedBy($user);

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->dispatchCreationNotification($data);

        return $result;
    }

    private function dispatchCreationNotification(ActivityPlanning $planning): void
    {
        try {
            $this->messageBus->dispatch(new ActivityPlanningCreatedNotification(
                planningId: $planning->getId() ?? 0,
                providerEmail: $planning->getProviderEmail() ?? '',
                providerName: $planning->getProvider() ?? '',
                process: $planning->getProcess() ?? '',
                siteCode: $planning->getSiteCode() ?? '',
                siteName: $planning->getSiteName() ?? '',
            ));
        } catch (\Throwable $e) {
            $this->logger->error('[Planning] Échec dispatch notification de création', [
                'planningId' => $planning->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
