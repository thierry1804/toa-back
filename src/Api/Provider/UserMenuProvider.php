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

        $accessQb = $this->entityManager->createQueryBuilder();
        $accessQb->select('IDENTITY(a.menu)')
            ->from(MenuAccess::class, 'a')
            ->where('a.canView = :canView')
            ->andWhere('a.role IN (:roles)')
            ->setParameter('canView', true)
            ->setParameter('roles', $userRoles);

        $accessibleMenuIds = array_map('intval', array_unique(
            $accessQb->getQuery()->getSingleColumnResult()
        ));

        $publicQb = $this->entityManager->createQueryBuilder();
        $publicQb->select('m.id')
            ->from(Menu::class, 'm')
            ->leftJoin('m.accessRules', 'a')
            ->where('a.id IS NULL')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', true);

        $publicMenuIds = array_map('intval', array_unique(
            $publicQb->getQuery()->getSingleColumnResult()
        ));

        $allIds = array_unique(array_merge($accessibleMenuIds, $publicMenuIds));

        if (empty($allIds)) {
            return [];
        }

        $menuQb = $this->entityManager->createQueryBuilder();
        $menuQb->select('m')
            ->from(Menu::class, 'm')
            ->leftJoin('m.children', 'c')
            ->where('m.id IN (:ids)')
            ->andWhere('m.isActive = :active')
            ->andWhere('m.parent IS NULL')
            ->orderBy('m.position', 'ASC')
            ->setParameter('ids', $allIds)
            ->setParameter('active', true);

        $rootMenus = $menuQb->getQuery()->getResult();

        return array_map(fn(Menu $menu) => $this->serializeMenu($menu, $allIds), $rootMenus);
    }

    private function serializeMenu(Menu $menu, array $accessibleIds): array
    {
        $children = [];
        foreach ($menu->getChildren() as $child) {
            if (!$child->isActive()) {
                continue;
            }
            if (!in_array($child->getId(), $accessibleIds, true)) {
                continue;
            }
            $children[] = $this->serializeMenu($child, $accessibleIds);
        }

        return [
            'id' => $menu->getId(),
            'name' => $menu->getName(),
            'icon' => $menu->getIcon(),
            'route' => $menu->getRoute(),
            'position' => $menu->getPosition(),
            'children' => $children,
        ];
    }
}
