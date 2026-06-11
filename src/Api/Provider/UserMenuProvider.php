<?php

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Menu\Entity\Menu;
use App\Domain\Menu\Entity\MenuAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserMenuProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token || !\is_object($token->getUser())) {
            return [];
        }

        $user = $token->getUser();
        $userRoles = $user->getRoles();

        $accessRules = $this->entityManager->createQueryBuilder()
            ->select('a, m')
            ->from(MenuAccess::class, 'a')
            ->join('a.menu', 'm')
            ->where('a.role IN (:roles)')
            ->setParameter('roles', $userRoles)
            ->getQuery()
            ->getResult();

        $permissions = [];
        foreach ($accessRules as $rule) {
            $menuId = $rule->getMenu()->getId();
            if (!isset($permissions[$menuId])) {
                $permissions[$menuId] = [
                    'canView' => false,
                    'canCreate' => false,
                    'canEdit' => false,
                    'canDelete' => false,
                ];
            }
            $p = &$permissions[$menuId];
            $p['canView'] = $p['canView'] || $rule->getCanView();
            $p['canCreate'] = $p['canCreate'] || $rule->getCanCreate();
            $p['canEdit'] = $p['canEdit'] || $rule->getCanEdit();
            $p['canDelete'] = $p['canDelete'] || $rule->getCanDelete();
        }

        $accessibleMenuIds = array_keys(array_filter($permissions, fn(array $p): bool => $p['canView']));

        $publicQb = $this->entityManager->createQueryBuilder();
        $publicQb->select('m')
            ->from(Menu::class, 'm')
            ->leftJoin('m.accessRules', 'a')
            ->where('a.id IS NULL')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', true);

        foreach ($publicQb->getQuery()->getResult() as $publicMenu) {
            $permissions[$publicMenu->getId()] = [
                'canView' => true,
                'canCreate' => true,
                'canEdit' => true,
                'canDelete' => true,
            ];
            $accessibleMenuIds[] = $publicMenu->getId();
        }

        $accessibleMenuIds = array_unique(array_map('intval', $accessibleMenuIds));

        if (empty($accessibleMenuIds)) {
            return [];
        }

        $menuQb = $this->entityManager->createQueryBuilder();
        $menuQb->select('m')
            ->from(Menu::class, 'm')
            ->where('m.id IN (:ids)')
            ->andWhere('m.isActive = :active')
            ->andWhere('m.parent IS NULL')
            ->orderBy('m.position', 'ASC')
            ->setParameter('ids', $accessibleMenuIds)
            ->setParameter('active', true);

        $rootMenus = $menuQb->getQuery()->getResult();

        return array_map(
            fn(Menu $menu) => $this->serializeMenu($menu, $accessibleMenuIds, $permissions),
            $rootMenus
        );
    }

    private function serializeMenu(Menu $menu, array $accessibleIds, array $permissions): array
    {
        $children = [];
        foreach ($menu->getChildren() as $child) {
            if (!$child->isActive()) {
                continue;
            }
            if (!in_array($child->getId(), $accessibleIds, true)) {
                continue;
            }
            $children[] = $this->serializeMenu($child, $accessibleIds, $permissions);
        }

        $p = $permissions[$menu->getId()] ?? [
            'canView' => false,
            'canCreate' => false,
            'canEdit' => false,
            'canDelete' => false,
        ];

        return [
            'id' => $menu->getId(),
            'name' => $menu->getName(),
            'icon' => $menu->getIcon(),
            'route' => $menu->getRoute(),
            'position' => $menu->getPosition(),
            'children' => $children,
            'canView' => $p['canView'],
            'canCreate' => $p['canCreate'],
            'canEdit' => $p['canEdit'],
            'canDelete' => $p['canDelete'],
        ];
    }
}
