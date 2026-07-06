<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use App\Domain\PlanPrevention\Entity\PlanPrevention;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PlanPreventionNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifierHse(PlanPrevention $plan, int $numeroVersion): void
    {
        $hseUsers = $this->userRepository->findByRole('ROLE_HSE');

        if (empty($hseUsers)) {
            $this->logger->info('[PlanPrevention] notifierHse: aucun utilisateur ROLE_HSE trouvé', [
                'plan_reference'  => $plan->getReference(),
                'numero_version'  => $numeroVersion,
            ]);

            return;
        }

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new Email())
                    ->from('noreply@toa.app')
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] Plan #%s resoumis (v%d)', $plan->getReference(), $numeroVersion))
                    ->text($this->buildResoumisText($plan, $hseUser, $numeroVersion))
                    ->html($this->buildResoumisHtml($plan, $hseUser, $numeroVersion));

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

    private function buildResoumisText(PlanPrevention $plan, User $recipient, int $numeroVersion): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le Plan de Prévention suivant a été resoumis par le prestataire et requiert votre décision :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Activité  : %s\n"
            . "  Version   : v%d\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipient->getFirstname(),
            $recipient->getName(),
            $plan->getReference(),
            $plan->getCodeSite(),
            $plan->getActivitePlanifiee(),
            $numeroVersion,
        );
    }

    private function buildResoumisHtml(PlanPrevention $plan, User $recipient, int $numeroVersion): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #2980b9;">Plan de Prévention resoumis</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le Plan de Prévention suivant a été resoumis par le prestataire et requiert votre décision :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Activité</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Version</strong></td><td style="padding: 4px 8px;">v%d</td></tr>
    </table>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $recipient->getFirstname()),
            htmlspecialchars((string) $recipient->getName()),
            htmlspecialchars((string) $plan->getReference()),
            htmlspecialchars((string) $plan->getCodeSite()),
            htmlspecialchars((string) $plan->getActivitePlanifiee()),
            $numeroVersion,
        );
    }

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
                $this->sendEmail($plan, $chefProjet, $hseUser);
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

    private function sendEmail(PlanPrevention $plan, User $chefProjet, User $recipient): void
    {
        $email = (new Email())
            ->from('noreply@toa.app')
            ->to((string) $recipient->getEmail())
            ->subject(sprintf('[TOA] Plan de Prévention #%s à valider', $plan->getReference()))
            ->text($this->buildText($plan, $chefProjet, $recipient))
            ->html($this->buildHtml($plan, $chefProjet, $recipient));

        $this->mailer->send($email);
    }

    private function buildText(PlanPrevention $plan, User $chefProjet, User $recipient): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le Plan de Prévention suivant a été examiné et requiert votre validation :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Activité  : %s\n\n"
            . "Examiné par : %s %s\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipient->getFirstname(),
            $recipient->getName(),
            $plan->getReference(),
            $plan->getCodeSite(),
            $plan->getActivitePlanifiee(),
            $chefProjet->getFirstname(),
            $chefProjet->getName(),
        );
    }

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
            $email = (new Email())
                ->from('noreply@toa.app')
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Plan de Prévention #%s refusé', $plan->getReference()))
                ->text($this->buildRefusText($plan, $prestataire, $commentaire))
                ->html($this->buildRefusHtml($plan, $prestataire, $commentaire));

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

    private function buildRefusText(PlanPrevention $plan, User $prestataire, string $commentaire): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Votre Plan de Prévention a été refusé par l'équipe HSE :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Activité  : %s\n\n"
            . "Motif du refus :\n%s\n\n"
            . "Merci de corriger votre plan et de le soumettre à nouveau.\n\n"
            . "Cordialement,\nL'équipe TOA",
            $prestataire->getFirstname(),
            $prestataire->getName(),
            $plan->getReference(),
            $plan->getCodeSite(),
            $plan->getActivitePlanifiee(),
            $commentaire,
        );
    }

    private function buildRefusHtml(PlanPrevention $plan, User $prestataire, string $commentaire): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #c0392b;">Plan de Prévention refusé</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Votre Plan de Prévention a été refusé par l\'équipe HSE :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Activité</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <h3>Motif du refus</h3>
    <p style="background: #fdf2f2; border-left: 4px solid #c0392b; padding: 12px;">%s</p>
    <p>Merci de corriger votre plan et de le soumettre à nouveau.</p>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $prestataire->getFirstname()),
            htmlspecialchars((string) $prestataire->getName()),
            htmlspecialchars((string) $plan->getReference()),
            htmlspecialchars((string) $plan->getCodeSite()),
            htmlspecialchars((string) $plan->getActivitePlanifiee()),
            nl2br(htmlspecialchars($commentaire)),
        );
    }

    private function buildHtml(PlanPrevention $plan, User $chefProjet, User $recipient): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Plan de Prévention à valider</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le Plan de Prévention suivant a été examiné et requiert votre validation :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Activité</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Examiné par</strong></td><td style="padding: 4px 8px;">%s %s</td></tr>
    </table>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $recipient->getFirstname()),
            htmlspecialchars((string) $recipient->getName()),
            htmlspecialchars((string) $plan->getReference()),
            htmlspecialchars((string) $plan->getCodeSite()),
            htmlspecialchars((string) $plan->getActivitePlanifiee()),
            htmlspecialchars((string) $chefProjet->getFirstname()),
            htmlspecialchars((string) $chefProjet->getName()),
        );
    }
}
