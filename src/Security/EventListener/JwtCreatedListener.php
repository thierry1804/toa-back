<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Domain\Role\Repository\RoleActionRepository;
use App\Domain\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
class JwtCreatedListener
{
    public function __construct(private readonly RoleActionRepository $roleActionRepository) {}

    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $permissions = $this->roleActionRepository->findActionKeysByRoles($user->getRoles());

        $payload = $event->getData();
        $payload['permissions'] = array_values(array_unique($permissions));
        $event->setData($payload);
    }
}
