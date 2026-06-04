<?php

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Domain\User\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserPasswordHasherProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User && $data->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $data,
                $data->getPlainPassword()
            );
            $data->setPassword($hashedPassword);
            $data->eraseCredentials();
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
