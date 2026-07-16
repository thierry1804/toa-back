<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * TEMPORARY — delete this file after use.
 */
class TempSetupController
{
    private const SECRET = 'toa-setup-2026';

    private const KEEP_MIGRATIONS = [
        'Version20260716200000.php',
        'Version20260716300000.php',
        'Version20260716400000.php',
    ];

    public function __construct(private readonly KernelInterface $kernel) {}

    #[Route('/internal/temp-setup', name: 'temp_setup', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->headers->get('X-Setup-Secret') !== self::SECRET) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $results = [];

        // ── 1. Purge stale migration files deployment left behind ─────────────────
        $migrationsDir = $this->kernel->getProjectDir() . '/migrations';
        $purged = [];
        foreach (glob($migrationsDir . '/Version*.php') as $file) {
            $basename = basename($file);
            if (!in_array($basename, self::KEEP_MIGRATIONS, true)) {
                unlink($file);
                $purged[] = $basename;
            }
        }
        $results[] = ['step' => 'purge_stale_migrations', 'deleted' => $purged];

        // ── 2. doctrine:migrations:migrate ───────────────────────────────────────
        $app = new Application($this->kernel);
        $app->setAutoExit(false);

        foreach ([
            ['command' => 'doctrine:migrations:migrate', '--no-interaction' => true],
            ['command' => 'app:setup:fix-week'],
        ] as $args) {
            $output = new BufferedOutput();
            try {
                $code = $app->run(new ArrayInput($args), $output);
                $results[] = [
                    'command' => $args['command'],
                    'status'  => $code === 0 ? 'ok' : 'failed',
                    'code'    => $code,
                    'output'  => $output->fetch(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'command' => $args['command'],
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                    'output'  => $output->fetch(),
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }
}
