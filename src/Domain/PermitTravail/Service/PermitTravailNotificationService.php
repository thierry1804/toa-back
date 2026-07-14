<?php

declare(strict_types=1);

namespace App\Domain\PermitTravail\Service;

use App\Domain\PermitTravail\Entity\PermitTravail;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PermitTravailNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifierValidation(PermitTravail $permit): void
    {
        $prestataire = $permit->getCreatedBy();

        if (!$prestataire instanceof User) {
            $this->logger->warning('[PermitTravail] notifierValidation: prestataire introuvable', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        try {
            $email = (new Email())
                ->from('noreply@toa.app')
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Permis de Travail #%s validé', $permit->getReference()))
                ->text($this->buildValidationText($permit, $prestataire))
                ->html($this->buildValidationHtml($permit, $prestataire));

            $this->mailer->send($email);

            $this->logger->info('[PermitTravail] Notification validation envoyée', [
                'permit_reference' => $permit->getReference(),
                'recipient'        => $prestataire->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[PermitTravail] Échec envoi notification validation', [
                'permit_reference' => $permit->getReference(),
                'recipient'        => $prestataire->getEmail(),
                'error'            => $e->getMessage(),
            ]);
        }
    }

    public function notifierRefus(PermitTravail $permit, string $commentaire): void
    {
        $prestataire = $permit->getCreatedBy();

        if (!$prestataire instanceof User) {
            $this->logger->warning('[PermitTravail] notifierRefus: prestataire introuvable', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        try {
            $email = (new Email())
                ->from('noreply@toa.app')
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Permis de Travail #%s refusé', $permit->getReference()))
                ->text($this->buildRefusText($permit, $prestataire, $commentaire))
                ->html($this->buildRefusHtml($permit, $prestataire, $commentaire));

            $this->mailer->send($email);

            $this->logger->info('[PermitTravail] Notification refus envoyée', [
                'permit_reference' => $permit->getReference(),
                'recipient'        => $prestataire->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[PermitTravail] Échec envoi notification refus', [
                'permit_reference' => $permit->getReference(),
                'recipient'        => $prestataire->getEmail(),
                'error'            => $e->getMessage(),
            ]);
        }
    }

    private function buildValidationText(PermitTravail $permit, User $prestataire): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Votre Permis de Travail a été validé par l'équipe HSE :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Type      : %s\n\n"
            . "Cordialement,\nL'équipe TOA",
            $prestataire->getFirstname(),
            $prestataire->getName(),
            $permit->getReference(),
            $permit->getCodeSite(),
            $permit->getType()?->value,
        );
    }

    private function buildValidationHtml(PermitTravail $permit, User $prestataire): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #27ae60;">Permis de Travail validé</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Votre Permis de Travail a été validé par l\'équipe HSE :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $prestataire->getFirstname()),
            htmlspecialchars((string) $prestataire->getName()),
            htmlspecialchars((string) $permit->getReference()),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
        );
    }

    private function buildRefusText(PermitTravail $permit, User $prestataire, string $commentaire): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Votre Permis de Travail a été refusé par l'équipe HSE :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Type      : %s\n\n"
            . "Motif du refus :\n%s\n\n"
            . "Merci de corriger votre dossier et de le soumettre à nouveau.\n\n"
            . "Cordialement,\nL'équipe TOA",
            $prestataire->getFirstname(),
            $prestataire->getName(),
            $permit->getReference(),
            $permit->getCodeSite(),
            $permit->getType()?->value,
            $commentaire,
        );
    }

    public function notifierHseResoumission(PermitTravail $permit, int $numeroVersion): void
    {
        $hseUsers = $this->userRepository->findByRole('ROLE_HSE');

        if (empty($hseUsers)) {
            $this->logger->info('[PermitTravail] notifierHseResoumission: aucun utilisateur ROLE_HSE trouvé', [
                'permit_reference' => $permit->getReference(),
                'numero_version'   => $numeroVersion,
            ]);

            return;
        }

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new Email())
                    ->from('noreply@toa.app')
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] Permis %s resoumis (v%d)', $permit->getReference(), $numeroVersion))
                    ->text($this->buildResoumissionText($permit, $hseUser, $numeroVersion))
                    ->html($this->buildResoumissionHtml($permit, $hseUser, $numeroVersion));

                $this->mailer->send($email);

                $this->logger->info('[PermitTravail] Notification resoumission envoyée', [
                    'permit_reference' => $permit->getReference(),
                    'numero_version'   => $numeroVersion,
                    'recipient'        => $hseUser->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PermitTravail] Échec envoi notification resoumission', [
                    'permit_reference' => $permit->getReference(),
                    'numero_version'   => $numeroVersion,
                    'recipient'        => $hseUser->getEmail(),
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildResoumissionText(PermitTravail $permit, User $recipient, int $numeroVersion): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le Permis de Travail suivant a été resoumis par le prestataire et requiert votre décision :\n\n"
            . "  Référence : %s\n"
            . "  Type      : %s\n"
            . "  Site      : %s\n"
            . "  Version   : v%d\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipient->getFirstname(),
            $recipient->getName(),
            $permit->getReference(),
            $permit->getType()?->value,
            $permit->getCodeSite(),
            $numeroVersion,
        );
    }

    private function buildResoumissionHtml(PermitTravail $permit, User $recipient, int $numeroVersion): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #2980b9;">Permis de Travail resoumis</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le Permis de Travail suivant a été resoumis par le prestataire et requiert votre décision :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Version</strong></td><td style="padding: 4px 8px;">v%d</td></tr>
    </table>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $recipient->getFirstname()),
            htmlspecialchars((string) $recipient->getName()),
            htmlspecialchars((string) $permit->getReference()),
            htmlspecialchars((string) $permit->getType()?->value),
            htmlspecialchars((string) $permit->getCodeSite()),
            $numeroVersion,
        );
    }

    private function buildRefusHtml(PermitTravail $permit, User $prestataire, string $commentaire): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #c0392b;">Permis de Travail refusé</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Votre Permis de Travail a été refusé par l\'équipe HSE :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <h3>Motif du refus</h3>
    <p style="background: #fdf2f2; border-left: 4px solid #c0392b; padding: 12px;">%s</p>
    <p>Merci de corriger votre dossier et de le soumettre à nouveau.</p>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $prestataire->getFirstname()),
            htmlspecialchars((string) $prestataire->getName()),
            htmlspecialchars((string) $permit->getReference()),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
            nl2br(htmlspecialchars($commentaire)),
        );
    }
}
