<?php

declare(strict_types=1);

namespace App\Api\Resource;

class PermitTravailSuivi
{
    public int $totalPermis = 0;
    public array $parStatut = [];
    public array $parType = [];
    public array $parSite = [];
    public array $expirantSous7j = [];
    public array $logsRecents = [];
}
