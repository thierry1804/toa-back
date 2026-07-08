<?php

declare(strict_types=1);

namespace App\Domain\Role\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Role\Entity\Role;
use App\Domain\Role\Repository\RoleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RoleProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $processor,
        private RoleRepository $roleRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Role) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        $isUpdate = isset($uriVariables['id']);

        if ($isUpdate) {
            $existing = $this->roleRepository->find($uriVariables['id']);
            if ($existing?->isSystem() && $data->getName() !== $existing->getName()) {
                throw new UnprocessableEntityHttpException('role_system_name_immutable');
            }
        }

        $name = strtoupper($data->getName());
        if (!str_starts_with($name, 'ROLE_')) {
            $name = 'ROLE_' . $name;
        }
        $data->setName($name);

        $duplicate = $this->roleRepository->findByName($data->getName());
        if ($duplicate !== null && $duplicate->getId() !== $data->getId()) {
            throw new UnprocessableEntityHttpException('role_name_already_exists');
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
