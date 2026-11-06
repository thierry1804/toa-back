<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Entreprise\Entity\SousTraitant;
use App\Domain\User\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SousTraitantWriteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof SousTraitant) {
            $actor = $this->tokenStorage->getToken()?->getUser();
            if ($actor instanceof User && $actor->getEntreprise() !== null && !$this->isBypass($actor)) {
                $data->setEntreprise($actor->getEntreprise());
            }
            if ($data->getEntreprise() === null) {
                throw new UnprocessableEntityHttpException('sous_traitant.entreprise_required');
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function isBypass(User $user): bool
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_HSE', 'ROLE_DIRECTION'] as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return true;
            }
        }

        return false;
    }
}
