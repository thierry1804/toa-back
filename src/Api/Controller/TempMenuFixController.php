<?php

declare(strict_types=1);

namespace App\Api\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TEMPORARY — delete after server menu fix is confirmed.
 * POST /internal/temp-menu-fix
 * Header: X-Setup-Secret: toa-setup-2026
 */
class TempMenuFixController
{
    private const SECRET = 'toa-setup-2026';

    public function __construct(private readonly KernelInterface $kernel) {}

    #[Route('/internal/temp-menu-fix', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->headers->get('X-Setup-Secret') !== self::SECRET) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $app = new Application($this->kernel);
        $app->setAutoExit(false);
        $log = [];

        $out = new BufferedOutput();
        $code = $app->run(new ArrayInput([
            'command'          => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
        ]), $out);
        $log['migrations'] = ['code' => $code, 'output' => $out->fetch()];

        $out = new BufferedOutput();
        $code = $app->run(new ArrayInput(['command' => 'app:setup:fix-week']), $out);
        $log['fix_week'] = ['code' => $code, 'output' => $out->fetch()];

        $out = new BufferedOutput();
        $code = $app->run(new ArrayInput(['command' => 'app:menu:seed']), $out);
        $log['menu_seed'] = ['code' => $code, 'output' => $out->fetch()];

        return new JsonResponse($log);
    }
}
