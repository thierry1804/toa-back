<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Domain\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
class AuthenticationSuccessListener
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data['user'] = [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'name'      => $user->getName(),
            'firstname' => $user->getFirstname(),
            'roles'     => $user->getRoles(),
            'isActive'  => $user->getIsActive(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
        $event->setData($data);
    }
}
