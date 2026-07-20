<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class PlanPreventionNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress = 'noreply@toa.app',
    ) {
    }

    /** Notify all HSE users when a plan is (re)submitted */
    public function notifierHse(PlanPrevention $plan, int $numeroVersion): void
    {
        $hseUsers = $this->userRepository->findByRole('ROLE_HSE');

        if (empty($hseUsers)) {
            $this->logger->info('[PlanPrevention] notifierHse: aucun utilisateur ROLE_HSE trouvé', [
                'plan_reference' => $plan->getReference(),
                'numero_version' => $numeroVersion,
            ]);

            return;
        }

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new TemplatedEmail())
                    ->from($this->fromAddress)
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] Plan #%s resoumis (v%d)', $plan->getReference(), $numeroVersion))
                    ->htmlTemplate('email/plan_prevention/soumis.html.twig')
                    ->context([
                        'plan'      => $plan,
                        'recipient' => $hseUser,
                        'version'   => $numeroVersion,
                    ]);

                $this->mailer->send($email);

                $this->logger->info('[PlanPrevention] Notification resoumission envoyée', [
                    'plan_reference' => $plan->getReference(),
                    'numero_version' => $numeroVersion,
                    'recipient'      => $hseUser->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PlanPrevention] Échec envoi notification resoumission', [
                    'plan_reference' => $plan->getReference(),
                    'numero_version' => $numeroVersion,
                    'recipient'      => $hseUser->getEmail(),
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }

    /** Notify all HSE users after plan has been examined (requires HSE validation) */
    public function notifyHseUsers(PlanPrevention $plan, User $chefProjet): void
    {
        $hseUsers = $this->userRepository->findByRole('ROLE_HSE');

        if (empty($hseUsers)) {
            $this->logger->info('[PlanPrevention] Notification: aucun utilisateur ROLE_HSE trouvé', [
                'plan_reference' => $plan->getReference(),
            ]);

            return;
        }

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new TemplatedEmail())
                    ->from($this->fromAddress)
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] Plan de Prévention #%s à valider', $plan->getReference()))
                    ->htmlTemplate('email/plan_prevention/soumis.html.twig')
                    ->context([
                        'plan'      => $plan,
                        'recipient' => $hseUser,
                    ]);

                $this->mailer->send($email);

                $this->logger->info('[PlanPrevention] Notification email envoyé', [
                    'plan_reference' => $plan->getReference(),
                    'recipient'      => $hseUser->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PlanPrevention] Échec envoi email notification', [
                    'plan_reference' => $plan->getReference(),
                    'recipient'      => $hseUser->getEmail(),
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }

    /** Notify the prestataire when their plan is refused */
    public function notifierPrestataire(PlanPrevention $plan, string $commentaire): void
    {
        $prestataire = $plan->getCreatedBy();

        if (!$prestataire instanceof User) {
            $this->logger->warning('[PlanPrevention] notifierPrestataire: prestataire introuvable', [
                'plan_reference' => $plan->getReference(),
            ]);

            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from($this->fromAddress)
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Plan de Prévention #%s refusé', $plan->getReference()))
                ->htmlTemplate('email/plan_prevention/refuse.html.twig')
                ->context([
                    'plan'        => $plan,
                    'recipient'   => $prestataire,
                    'commentaire' => $commentaire,
                ]);

            $this->mailer->send($email);

            $this->logger->info('[PlanPrevention] Notification refus envoyée au prestataire', [
                'plan_reference' => $plan->getReference(),
                'recipient'      => $prestataire->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[PlanPrevention] Échec envoi notification refus prestataire', [
                'plan_reference' => $plan->getReference(),
                'recipient'      => $prestataire->getEmail(),
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
