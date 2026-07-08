<?php

declare(strict_types=1);

namespace App\Domain\Menu\Service;

use App\Domain\Menu\Entity\MenuAccess;
use App\Domain\Menu\Repository\MenuAccessRepository;
use App\Domain\Role\Entity\RoleAction;
use App\Domain\Role\Repository\RoleActionRepository;

class PermissionChecker
{
    public function __construct(
        private MenuAccessRepository $repository,
        private RoleActionRepository $roleActionRepository,
    ) {
    }

    /**
     * Returns true if any of the given roles have the requested action on the menu route.
     * A route with no access rules is considered public and always grants access.
     */
    public function isGranted(array $roles, string $menuRoute, string $action): bool
    {
        if (!$this->repository->routeHasAccessRules($menuRoute)) {
            return true;
        }

        foreach ($this->repository->findByRolesAndRoute($roles, $menuRoute) as $record) {
            if ($this->actionAllowed($record, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if any of the given roles have the domain action key.
     */
    public function hasAction(array $roles, string $actionKey): bool
    {
        return $this->roleActionRepository->existsByRolesAndAction($roles, $actionKey);
    }

    /**
     * Returns all RoleAction records matching roles + actionKey.
     * Used to inspect bypassOwnership per matching role.
     *
     * @return RoleAction[]
     */
    public function getRoleActions(array $roles, string $actionKey): array
    {
        return $this->roleActionRepository->findByRolesAndAction($roles, $actionKey);
    }

    private function actionAllowed(MenuAccess $record, string $action): bool
    {
        return match ($action) {
            'VIEW'   => $record->getCanView(),
            'CREATE' => $record->getCanCreate(),
            'EDIT'   => $record->getCanEdit(),
            'DELETE' => $record->getCanDelete(),
            default  => false,
        };
    }
}
