<?php

declare(strict_types=1);

namespace App\Api\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint de diagnostic : envoie un email de test en direct (hors file
 * Messenger) afin de retourner immédiatement le résultat réel de la
 * transaction SMTP — commandes/réponses du serveur incluses — que l'envoi
 * réussisse ou échoue.
 *
 * Public, sans authentification (voir access_control dans security.yaml).
 */
#[Route('/api/system/mail-test', name: 'system_mail_test', methods: ['POST'])]
final class SystemMailTestController extends AbstractController
{
    // Borne l'attente réseau pour que l'endpoint réponde toujours avec un
    // détail exploitable plutôt que de rester bloqué jusqu'au timeout
    // nginx/php-fpm (qui renverrait une page d'erreur générique sans log).
    private const SOCKET_TIMEOUT_SECONDS = 15.0;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private readonly string $fromAddress,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $fromName,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true) ?? [];
        $to      = (string) ($payload['email'] ?? $request->query->get('email') ?? '');

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'error'   => 'Adresse email invalide ou manquante (paramètre "email" attendu).',
            ], 422);
        }

        $log = [];
        $step = static function (string $message) use (&$log): void {
            $log[] = [
                'time'    => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v'),
                'message' => $message,
            ];
        };

        try {
            $dsn = Dsn::fromString($this->mailerDsn);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'to'      => $to,
                'error'   => [
                    'class'   => $e::class,
                    'message' => 'DSN MAILER_DSN invalide : ' . $e->getMessage(),
                ],
            ], 500);
        }
        $scheme = $dsn->getScheme();
        $host   = $dsn->getHost();
        $port   = $dsn->getPort();
        $user   = $dsn->getUser();

        $step(sprintf(
            'Configuration lue : %s://%s%s:%s (mot de passe masqué)',
            $scheme,
            $user !== null ? $user . '@' : '',
            $host,
            $port ?? '?',
        ));

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to($to)
            ->subject('[TOA] Email de test')
            ->text(sprintf(
                "Ceci est un email de test envoyé depuis la plateforme TOA HSE le %s pour vérifier l'acheminement des emails.\n\nSi vous recevez ce message, la configuration SMTP fonctionne correctement.",
                (new \DateTimeImmutable())->format('d/m/Y H:i:s'),
            ))
            ->html(sprintf(
                '<p>Ceci est un email de test envoyé depuis la plateforme <strong>TOA HSE</strong> le %s pour vérifier l\'acheminement des emails.</p><p>Si vous recevez ce message, la configuration SMTP fonctionne correctement.</p>',
                (new \DateTimeImmutable())->format('d/m/Y H:i:s'),
            ));

        $step(sprintf('Email construit : from=%s, to=%s', $this->fromAddress, $to));

        $collector = new class extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->lines[] = sprintf('[%s] %s', strtoupper((string) $level), $message);
            }
        };

        try {
            $transport = Transport::fromDsn($this->mailerDsn, $this->dispatcher, null, $collector);
            if ($transport instanceof SmtpTransport) {
                $stream = $transport->getStream();
                if ($stream instanceof SocketStream) {
                    $stream->setTimeout(self::SOCKET_TIMEOUT_SECONDS);
                }
            }
        } catch (\Throwable $e) {
            $step('Échec de construction du transport SMTP : ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'to'      => $to,
                'host'    => $host,
                'port'    => $port,
                'error'   => [
                    'class'   => $e::class,
                    'message' => $e->getMessage(),
                ],
                'log' => $log,
            ], 500);
        }

        $step('Connexion au serveur SMTP et envoi en cours...');
        $start = microtime(true);

        try {
            $sentMessage = $transport->send($email);
            $durationMs  = (int) round((microtime(true) - $start) * 1000);
            $step(sprintf('Email accepté par le serveur SMTP en %d ms', $durationMs));

            return $this->json([
                'success'        => true,
                'to'             => $to,
                'from'           => $this->fromAddress,
                'host'           => $host,
                'port'           => $port,
                'durationMs'     => $durationMs,
                'messageId'      => $sentMessage?->getMessageId(),
                'log'            => $log,
                'transportLog'   => $collector->lines,
                'smtpTranscript' => $sentMessage?->getDebug(),
                'note'           => "L'acceptation par le serveur SMTP confirme l'envoi mais ne garantit pas la réception finale (filtres anti-spam, boîte pleine, etc.). Vérifiez la boîte de réception du destinataire.",
            ]);
        } catch (TransportExceptionInterface $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $step('Échec de l\'envoi : ' . $e->getMessage());

            return $this->json([
                'success'        => false,
                'to'             => $to,
                'from'           => $this->fromAddress,
                'host'           => $host,
                'port'           => $port,
                'durationMs'     => $durationMs,
                'error'          => [
                    'class'   => $e::class,
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ],
                'log'            => $log,
                'transportLog'   => $collector->lines,
                'smtpTranscript' => $e->getDebug(),
            ], 502);
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $step('Échec inattendu : ' . $e->getMessage());

            return $this->json([
                'success'      => false,
                'to'           => $to,
                'host'         => $host,
                'port'         => $port,
                'durationMs'   => $durationMs,
                'error'        => [
                    'class'   => $e::class,
                    'message' => $e->getMessage(),
                ],
                'log'          => $log,
                'transportLog' => $collector->lines,
            ], 500);
        }
    }
}
