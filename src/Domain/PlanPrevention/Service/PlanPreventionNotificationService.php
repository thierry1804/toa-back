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

    /**
     * Destinataires côté TOA : tous les chefs de projet titulaires du processus du plan (R-32/R-33),
     * à défaut le chef de projet nominativement assigné au plan.
     *
     * @return User[]
     */
    private function chefsProjetDuPlan(PlanPrevention $plan): array
    {
        $titulaires = $this->userRepository->findChefsProjetByProcessus($plan->getTypeIntervention());
        if ($titulaires !== []) {
            return $titulaires;
        }

        return $plan->getChefProjet() instanceof User ? [$plan->getChefProjet()] : [];
    }

    /** Notify the chefs de projet titulaires of the plan's processus when the prestataire submits it, so they can examine it */
    public function notifierChefProjet(PlanPrevention $plan): void
    {
        $chefsProjet = $this->chefsProjetDuPlan($plan);

        if ($chefsProjet === []) {
            $this->logger->warning('[PlanPrevention] notifierChefProjet: aucun chef de projet titulaire ni assigné au plan', [
                'plan_reference' => $plan->getReference(),
            ]);

            return;
        }

        foreach ($chefsProjet as $chefProjet) {
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
    }

    /**
     * R-31/D11 : tous les chefs de projet prestataire de l'entreprise du dossier sont informés
     * de la soumission (suivi en lecture seule, sans droit de validation).
     */
    public function notifierChefsProjetPrestataire(PlanPrevention $plan): void
    {
        $entrepriseId = $plan->getCreatedBy()?->getEntreprise()?->getId();
        if ($entrepriseId === null) {
            return;
        }

        foreach ($this->userRepository->findByRoleAndEntreprise('ROLE_CHEF_PROJET_PRESTATAIRE', (string) $entrepriseId) as $cpp) {
            try {
                $email = (new \Symfony\Component\Mime\Email())
                    ->from($this->fromAddress)
                    ->to((string) $cpp->getEmail())
                    ->subject(sprintf('[TOA] Plan de Prévention #%s soumis', $plan->getReference()))
                    ->text(sprintf(
                        "Bonjour %s,\n\nLe plan de prévention %s de votre entreprise a été soumis. Vous pouvez le suivre en lecture seule.%s\n\nCordialement,\nL'équipe TOA",
                        trim((string) $cpp->getFirstname()),
                        $plan->getReference(),
                        $this->planUrl($plan) !== null ? "\n\nConsulter le plan : " . $this->planUrl($plan) : '',
                    ));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('[PlanPrevention] Échec envoi notification chef de projet prestataire', [
                    'plan_reference' => $plan->getReference(),
                    'recipient'      => $cpp->getEmail(),
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * R-33 : la première décision d'un titulaire verrouille le dossier ; l'autre titulaire en est informé
     * (le nom du décideur reste dans l'historique du dossier).
     */
    public function informerCoTitulaires(PlanPrevention $plan, User $decideur, string $decision): void
    {
        foreach ($this->chefsProjetDuPlan($plan) as $titulaire) {
            if ($titulaire->getUserIdentifier() === $decideur->getUserIdentifier()) {
                continue;
            }

            try {
                $email = (new \Symfony\Component\Mime\Email())
                    ->from($this->fromAddress)
                    ->to((string) $titulaire->getEmail())
                    ->subject(sprintf('[TOA] Plan de Prévention #%s %s par un autre chef de projet', $plan->getReference(), $decision))
                    ->text(sprintf(
                        "Bonjour %s,\n\nLe plan de prévention %s a été %s par %s. Le dossier est désormais verrouillé pour vous.%s\n\nCordialement,\nL'équipe TOA",
                        trim((string) $titulaire->getFirstname()),
                        $plan->getReference(),
                        $decision,
                        trim($decideur->getFirstname() . ' ' . $decideur->getName()),
                        $this->planUrl($plan) !== null ? "\n\nConsulter le plan : " . $this->planUrl($plan) : '',
                    ));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('[PlanPrevention] Échec envoi information aux co-titulaires', [
                    'plan_reference' => $plan->getReference(),
                    'recipient'      => $titulaire->getEmail(),
                    'error'          => $e->getMessage(),
                ]);
            }
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

    /** Notify the prestataire when their plan is validated by the HSE team */
    public function notifierValidation(PlanPrevention $plan): void
    {
        $prestataire = $plan->getCreatedBy();

        if (!$prestataire instanceof User) {
            $this->logger->warning('[PlanPrevention] notifierValidation: prestataire introuvable', [
                'plan_reference' => $plan->getReference(),
            ]);

            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from($this->fromAddress)
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Plan de Prévention #%s validé', $plan->getReference()))
                ->htmlTemplate('email/plan_prevention/valide.html.twig')
                ->context([
                    'plan'      => $plan,
                    'plan_url'  => $this->planUrl($plan),
                    'recipient' => $prestataire,
                ]);

            $this->mailer->send($email);

            $this->logger->info('[PlanPrevention] Notification validation envoyée au prestataire', [
                'plan_reference' => $plan->getReference(),
                'recipient'      => $prestataire->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[PlanPrevention] Échec envoi notification validation prestataire', [
                'plan_reference' => $plan->getReference(),
                'recipient'      => $prestataire->getEmail(),
                'error'          => $e->getMessage(),
            ]);
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
