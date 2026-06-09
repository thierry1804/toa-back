<?php

namespace App\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Api\Provider\UserMenuProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/my-menu',
            provider: UserMenuProvider::class,
            paginationEnabled: false,
        ),
    ],
)]
class UserMenu
{
}
