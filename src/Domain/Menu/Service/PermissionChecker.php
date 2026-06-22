<?php

declare(strict_types=1);

namespace App\Domain\Menu\Service;

use App\Domain\Menu\Entity\MenuAccess;
use App\Domain\Menu\Repository\MenuAccessRepository;

class PermissionChecker
{
    public function __construct(private MenuAccessRepository $repository) {}

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
