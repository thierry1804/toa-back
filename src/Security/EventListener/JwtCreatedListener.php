<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Domain\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
class JwtCreatedListener
{
    private const ROLE_PERMISSIONS = [
        'ROLE_CHEF_PROJET'  => ['plan_prevention.examine'],
        'ROLE_HSE'          => ['plan_prevention.valider_hse', 'plan_prevention.refuser_hse'],
        'ROLE_ADMIN'        => ['plan_prevention.examine', 'plan_prevention.valider_hse', 'plan_prevention.refuser_hse'],
        'ROLE_SUPER_ADMIN'  => ['plan_prevention.examine', 'plan_prevention.valider_hse', 'plan_prevention.refuser_hse'],
    ];

    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $permissions = [];
        foreach ($user->getRoles() as $role) {
            if (isset(self::ROLE_PERMISSIONS[$role])) {
                foreach (self::ROLE_PERMISSIONS[$role] as $perm) {
                    $permissions[] = $perm;
                }
            }
        }

        $payload = $event->getData();
        $payload['permissions'] = array_values(array_unique($permissions));
        $event->setData($payload);
    }
}
