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

    /** Notify the HSE team attached to the plan's prestataire when it is (re)submitted */
    public function notifierHse(PlanPrevention $plan, int $numeroVersion): void
    {
        $hseUsers = $this->findHseTeamFor($plan);

        if (empty($hseUsers)) {
            $this->logger->info('[PlanPrevention] notifierHse: aucun utilisateur ROLE_HSE trouvé pour l\'entreprise du prestataire', [
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

    /** Notify the HSE team attached to the plan's prestataire after it has been examined (requires HSE validation) */
    public function notifyHseUsers(PlanPrevention $plan, User $chefProjet): void
    {
        $hseUsers = $this->findHseTeamFor($plan);

        if (empty($hseUsers)) {
            $this->logger->info('[PlanPrevention] Notification: aucun utilisateur ROLE_HSE trouvé pour l\'entreprise du prestataire', [
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

    /**
     * Resolves the HSE team scoped to the entreprise of the plan's creator
     * (the prestataire). Returns an empty array if the creator has no
     * entreprise — there is then no specific team to target.
     *
     * @return User[]
     */
    private function findHseTeamFor(PlanPrevention $plan): array
    {
        $entrepriseId = $plan->getCreatedBy()?->getEntreprise()?->getId();
        if ($entrepriseId === null) {
            return [];
        }

        return $this->userRepository->findByRoleAndEntreprise('ROLE_HSE', $entrepriseId);
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
