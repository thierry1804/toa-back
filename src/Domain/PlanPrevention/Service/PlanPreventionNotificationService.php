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
        private readonly string $frontendUrl = '',
    ) {
    }

    private function planUrl(PlanPrevention $plan): ?string
    {
        if ($this->frontendUrl === '') {
            return null;
        }

        return rtrim($this->frontendUrl, '/') . '/prevention/' . $plan->getId()?->toRfc4122();
    }

    /** Notify the chef de projet assigned to the plan when the prestataire submits it, so they can examine it */
    public function notifierChefProjet(PlanPrevention $plan): void
    {
        $chefProjet = $plan->getChefProjet();

        if (!$chefProjet instanceof User) {
            $this->logger->warning('[PlanPrevention] notifierChefProjet: aucun chef de projet assigné au plan', [
                'plan_reference' => $plan->getReference(),
            ]);

            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from($this->fromAddress)
                ->to((string) $chefProjet->getEmail())
                ->subject(sprintf('[TOA] Plan de Prévention #%s soumis — Examen requis', $plan->getReference()))
                ->htmlTemplate('email/plan_prevention/soumis.html.twig')
                ->context([
                    'plan'      => $plan,
                    'plan_url'  => $this->planUrl($plan),
                    'recipient' => $chefProjet,
                ]);

            $this->mailer->send($email);

            $this->logger->info('[PlanPrevention] Notification de soumission envoyée au chef de projet', [
                'plan_reference' => $plan->getReference(),
                'recipient'      => $chefProjet->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[PlanPrevention] Échec envoi notification de soumission au chef de projet', [
                'plan_reference' => $plan->getReference(),
                'recipient'      => $chefProjet->getEmail(),
                'error'          => $e->getMessage(),
            ]);
        }
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
                        'plan_url'  => $this->planUrl($plan),
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
                    ->htmlTemplate('email/plan_prevention/a_valider.html.twig')
                    ->context([
                        'plan'         => $plan,
                        'plan_url'     => $this->planUrl($plan),
                        'recipient'    => $hseUser,
                        'examinateur'  => $chefProjet,
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
     * Tous les utilisateurs ROLE_HSE sont notifiés, sans filtre par entreprise.
     *
     * @return User[]
     */
    private function findHseTeamFor(PlanPrevention $plan): array
    {
        return $this->userRepository->findByRole('ROLE_HSE');
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
                    'plan_url'    => $this->planUrl($plan),
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
