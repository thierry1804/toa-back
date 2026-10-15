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
        private readonly string $fromAddress = 'noreply@toa.app',
        private readonly string $frontendUrl = '',
    ) {
    }

    private function permitUrl(PermitTravail $permit): string
    {
        if ($this->frontendUrl === '') {
            return '';
        }

        return rtrim($this->frontendUrl, '/') . '/permits-travail/' . $permit->getId()?->toRfc4122();
    }

    private function referenceLink(PermitTravail $permit): string
    {
        $reference = htmlspecialchars((string) $permit->getReference());
        $url = $this->permitUrl($permit);
        if ($url === '') {
            return $reference;
        }

        return sprintf('<a href="%s">%s</a>', htmlspecialchars($url), $reference);
    }

    private function withLink(string $text, PermitTravail $permit): string
    {
        $url = $this->permitUrl($permit);
        if ($url === '') {
            return $text;
        }

        return $text . "

Accéder au permis : " . $url;
    }

    private function accessCtaHtml(PermitTravail $permit, string $label = 'Ouvrir le permis'): string
    {
        $url = $this->permitUrl($permit);
        if ($url === '') {
            return '';
        }

        $safeUrl = htmlspecialchars($url);
        $safeLabel = htmlspecialchars($label);

        return sprintf(
            '<table width="100%%" cellpadding="0" cellspacing="0" role="presentation" style="margin:24px 0 8px;">'
            . '<tr><td align="center">'
            . '<a href="%s" style="display:inline-block;background-color:#006633;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 28px;border-radius:8px;">%s</a>'
            . '</td></tr>'
            . '<tr><td align="center" style="padding-top:10px;">'
            . '<p style="margin:0;font-size:11px;color:#9ca3af;line-height:1.4;word-break:break-all;">%s</p>'
            . '</td></tr></table>',
            $safeUrl,
            $safeLabel,
            $safeUrl,
        );
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
                ->from($this->fromAddress)
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Permis de Travail #%s validé', $permit->getReference()))
                ->text($this->withLink($this->buildValidationText($permit, $prestataire), $permit))
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
                ->from($this->fromAddress)
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] Permis de Travail #%s refusé', $permit->getReference()))
                ->text($this->withLink($this->buildRefusText($permit, $prestataire, $commentaire), $permit))
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

    /**
     * Notifie l'équipe HSE (entreprise du prestataire) qu'un permis vient
     * d'être soumis pour la première fois (BROUILLON -> SOUMIS).
     */
    public function notifierSoumission(PermitTravail $permit): void
    {
        $hseUsers = $this->findHseTeamFor($permit);

        if (empty($hseUsers)) {
            $this->logger->info('[PermitTravail] notifierSoumission: aucun utilisateur ROLE_HSE trouvé pour l\'entreprise du prestataire', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new Email())
                    ->from($this->fromAddress)
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] Permis %s soumis pour validation', $permit->getReference()))
                    ->text($this->withLink($this->buildSoumissionText($permit, $hseUser), $permit))
                    ->html($this->buildSoumissionHtml($permit, $hseUser));

                $this->mailer->send($email);

                $this->logger->info('[PermitTravail] Notification soumission envoyée', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $hseUser->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PermitTravail] Échec envoi notification soumission', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $hseUser->getEmail(),
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildSoumissionText(PermitTravail $permit, User $recipient): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le Permis de Travail suivant a été soumis par le prestataire et requiert votre décision :\n\n"
            . "  Référence : %s\n"
            . "  Type      : %s\n"
            . "  Site      : %s\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipient->getFirstname(),
            $recipient->getName(),
            $permit->getReference(),
            $permit->getType()?->value,
            $permit->getCodeSite(),
        );
    }

    private function buildSoumissionHtml(PermitTravail $permit, User $recipient): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #2980b9;">Permis de Travail soumis</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le Permis de Travail suivant a été soumis par le prestataire et requiert votre décision :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $recipient->getFirstname()),
            htmlspecialchars((string) $recipient->getName()),
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getType()?->value),
            htmlspecialchars((string) $permit->getCodeSite()),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis à valider');
    }
    public function notifierClotureChefProjet(PermitTravail $permit): void
    {
        $plan = $permit->getPlanPrevention();

        // R-32/R-34 : tous les chefs de projet titulaires du processus valident le PV de clôture ;
        // à défaut, le chef de projet nominativement assigné au plan.
        $chefsProjet = $this->userRepository->findChefsProjetByProcessus($plan?->getTypeIntervention());
        if ($chefsProjet === [] && $plan?->getChefProjet() instanceof User) {
            $chefsProjet = [$plan->getChefProjet()];
        }

        if ($chefsProjet === []) {
            $this->logger->warning('[PermitTravail] notifierClotureChefProjet: chef de projet introuvable', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        foreach ($chefsProjet as $chefProjet) {
            try {
                $email = (new Email())
                    ->from($this->fromAddress)
                    ->to((string) $chefProjet->getEmail())
                    ->subject(sprintf('[TOA] Permis de Travail #%s clôturé — vérification requise', $permit->getReference()))
                    ->text($this->withLink($this->buildClotureText($permit, $chefProjet), $permit))
                    ->html($this->buildClotureHtml($permit, $chefProjet));

                $this->mailer->send($email);

                $this->logger->info('[PermitTravail] Notification clôture chef de projet envoyée', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $chefProjet->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PermitTravail] Échec envoi notification clôture chef de projet', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $chefProjet->getEmail(),
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildClotureText(PermitTravail $permit, User $chefProjet): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le Permis de Travail suivant a été clôturé par le prestataire, avec ses documents de clôture :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Type      : %s\n\n"
            . "Merci de vérifier les documents de clôture dans la fiche du permis.\n\n"
            . "Cordialement,\nL'équipe TOA",
            $chefProjet->getFirstname(),
            $chefProjet->getName(),
            $permit->getReference(),
            $permit->getCodeSite(),
            $permit->getType()?->value,
        );
    }

    private function buildClotureHtml(PermitTravail $permit, User $chefProjet): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #27ae60;">Permis de Travail clôturé</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le Permis de Travail suivant a été clôturé par le prestataire, avec ses documents de clôture :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <p>Merci de vérifier les documents de clôture dans la fiche du permis.</p>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $chefProjet->getFirstname()),
            htmlspecialchars((string) $chefProjet->getName()),
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis');
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
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis validé');
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

    /**
     * Tous les utilisateurs ROLE_HSE sont notifiés, sans filtre par entreprise.
     *
     * @return User[]
     */
    private function findHseTeamFor(PermitTravail $permit): array
    {
        return $this->userRepository->findByRole('ROLE_HSE');
    }

    public function notifierHseResoumission(PermitTravail $permit, int $numeroVersion): void
    {
        $hseUsers = $this->findHseTeamFor($permit);

        if (empty($hseUsers)) {
            $this->logger->info('[PermitTravail] notifierHseResoumission: aucun utilisateur ROLE_HSE trouvé pour l\'entreprise du prestataire', [
                'permit_reference' => $permit->getReference(),
                'numero_version'   => $numeroVersion,
            ]);

            return;
        }

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new Email())
                    ->from($this->fromAddress)
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] Permis %s resoumis (v%d)', $permit->getReference(), $numeroVersion))
                    ->text($this->withLink($this->buildResoumissionText($permit, $hseUser, $numeroVersion), $permit))
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
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getType()?->value),
            htmlspecialchars((string) $permit->getCodeSite()),
            $numeroVersion,
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis à valider');
    }

    public function notifierHseArchivage(PermitTravail $permit, \DateTimeImmutable $dateValidation): void
    {
        $hseUsers = $this->findHseTeamFor($permit);

        if (empty($hseUsers)) {
            $this->logger->info('[PermitTravail] notifierHseArchivage: aucun utilisateur ROLE_HSE trouvé pour l\'entreprise du prestataire', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        $chefProjet = $permit->getPlanPrevention()?->getChefProjet();
        $chefProjetNom = $chefProjet !== null
            ? trim($chefProjet->getFirstname() . ' ' . $chefProjet->getName())
            : '—';

        foreach ($hseUsers as $hseUser) {
            try {
                $email = (new Email())
                    ->from($this->fromAddress)
                    ->to((string) $hseUser->getEmail())
                    ->subject(sprintf('[TOA] PV #%s validé — archivage requis', $permit->getReference()))
                    ->text($this->withLink($this->buildArchivageText($permit, $hseUser, $chefProjetNom, $dateValidation), $permit))
                    ->html($this->buildArchivageHtml($permit, $hseUser, $chefProjetNom, $dateValidation));

                $this->mailer->send($email);

                $this->logger->info('[PermitTravail] Notification archivage HSE envoyée', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $hseUser->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PermitTravail] Échec envoi notification archivage HSE', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $hseUser->getEmail(),
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildArchivageText(PermitTravail $permit, User $recipient, string $chefProjetNom, \DateTimeImmutable $dateValidation): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le PV de réception du Permis de Travail suivant a été validé par le Chef de Projet et requiert un archivage :\n\n"
            . "  Référence      : %s\n"
            . "  Site           : %s\n"
            . "  Chef de Projet : %s\n"
            . "  Date validation : %s\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipient->getFirstname(),
            $recipient->getName(),
            $permit->getReference(),
            $permit->getCodeSite(),
            $chefProjetNom,
            $dateValidation->format('d/m/Y H:i'),
        );
    }

    private function buildArchivageHtml(PermitTravail $permit, User $recipient, string $chefProjetNom, \DateTimeImmutable $dateValidation): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #27ae60;">PV validé — archivage requis</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le PV de réception du Permis de Travail suivant a été validé par le Chef de Projet et requiert un archivage :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Chef de Projet</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Date validation</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $recipient->getFirstname()),
            htmlspecialchars((string) $recipient->getName()),
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars($chefProjetNom),
            htmlspecialchars($dateValidation->format('d/m/Y H:i')),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis');
    }

    public function notifierPrestatairePvRefus(PermitTravail $permit, string $commentaire): void
    {
        $prestataire = $permit->getCreatedBy();

        if (!$prestataire instanceof User) {
            $this->logger->warning('[PermitTravail] notifierPrestatairePvRefus: prestataire introuvable', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        try {
            $email = (new Email())
                ->from($this->fromAddress)
                ->to((string) $prestataire->getEmail())
                ->subject(sprintf('[TOA] PV #%s refusé par Chef de Projet', $permit->getReference()))
                ->text($this->withLink($this->buildPvRefusText($permit, $prestataire, $commentaire), $permit))
                ->html($this->buildPvRefusHtml($permit, $prestataire, $commentaire));

            $this->mailer->send($email);

            $this->logger->info('[PermitTravail] Notification PV refus CDP envoyée', [
                'permit_reference' => $permit->getReference(),
                'recipient'        => $prestataire->getEmail(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[PermitTravail] Échec envoi notification PV refus CDP', [
                'permit_reference' => $permit->getReference(),
                'recipient'        => $prestataire->getEmail(),
                'error'            => $e->getMessage(),
            ]);
        }
    }

    private function buildPvRefusText(PermitTravail $permit, User $prestataire, string $commentaire): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le PV de réception du Permis de Travail suivant a été refusé par le Chef de Projet :\n\n"
            . "  Référence : %s\n"
            . "  Site      : %s\n"
            . "  Type      : %s\n\n"
            . "Motif du refus :\n%s\n\n"
            . "Veuillez procéder aux corrections nécessaires et clôturer à nouveau le permis.\n\n"
            . "Cordialement,\nL'équipe TOA",
            $prestataire->getFirstname(),
            $prestataire->getName(),
            $permit->getReference(),
            $permit->getCodeSite(),
            $permit->getType()?->value,
            $commentaire,
        );
    }

    private function buildPvRefusHtml(PermitTravail $permit, User $prestataire, string $commentaire): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #c0392b;">PV de réception refusé</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le PV de réception du Permis de Travail suivant a été refusé par le Chef de Projet :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <h3>Motif du refus</h3>
    <p style="background: #fdf2f2; border-left: 4px solid #c0392b; padding: 12px;">%s</p>
    <p>Veuillez procéder aux corrections nécessaires et clôturer à nouveau le permis.</p>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $prestataire->getFirstname()),
            htmlspecialchars((string) $prestataire->getName()),
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
            nl2br(htmlspecialchars($commentaire)),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis');
    }

    /**
     * R-27 : rappel 24 h avant l'échéance, adressé à l'équipe HSE du prestataire et à
     * l'intervenant, pour inviter au dépôt d'une demande de renouvellement.
     */
    public function notifierExpirationProchaine(PermitTravail $permit): void
    {
        $destinataires = $this->destinatairesRappelExpiration($permit);

        if ($destinataires === []) {
            $this->logger->warning('[PermitTravail] notifierExpirationProchaine: aucun destinataire', [
                'permit_reference' => $permit->getReference(),
            ]);

            return;
        }

        $dateFinPrevue = $permit->getDateFinPrevue();

        foreach ($destinataires as $destinataire) {
            try {
                $email = (new Email())
                    ->from($this->fromAddress)
                    ->to((string) $destinataire->getEmail())
                    ->subject(sprintf('[TOA] Permis de Travail #%s : échéance dans 24 h — renouvellement à demander', $permit->getReference()))
                    ->text($this->withLink($this->buildExpirationText($permit, $destinataire, $dateFinPrevue), $permit))
                    ->html($this->buildExpirationHtml($permit, $destinataire, $dateFinPrevue));

                $this->mailer->send($email);

                $this->logger->info('[PermitTravail] Notification expiration prochaine envoyée', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $destinataire->getEmail(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[PermitTravail] Échec envoi notification expiration prochaine', [
                    'permit_reference' => $permit->getReference(),
                    'recipient'        => $destinataire->getEmail(),
                    'error'            => $e->getMessage(),
                ]);
            }
        }
    }

    /** @return User[] sans doublon (par e-mail) */
    private function destinatairesRappelExpiration(PermitTravail $permit): array
    {
        $candidats = [];

        $createur = $permit->getCreatedBy();
        if ($createur instanceof User) {
            $candidats[] = $createur;

            $entrepriseId = $createur->getEntreprise()?->getId();
            if ($entrepriseId !== null) {
                foreach ($this->userRepository->findByRoleAndEntreprise('ROLE_HSE', (string) $entrepriseId) as $hse) {
                    $candidats[] = $hse;
                }
            }
        }

        $intervenant = $permit->getIntervention()?->getCreatedBy();
        if ($intervenant instanceof User) {
            $candidats[] = $intervenant;
        }

        $uniques = [];
        foreach ($candidats as $user) {
            $email = (string) $user->getEmail();
            if ($email !== '' && !isset($uniques[$email])) {
                $uniques[$email] = $user;
            }
        }

        return array_values($uniques);
    }

    private function buildExpirationText(PermitTravail $permit, User $prestataire, ?\DateTimeImmutable $dateFinPrevue): string
    {
        return sprintf(
            "Bonjour %s %s,\n\n"
            . "Le Permis de Travail suivant arrive à échéance dans environ 24 heures :\n\n"
            . "  Référence      : %s\n"
            . "  Site           : %s\n"
            . "  Type           : %s\n"
            . "  Date fin prévue : %s\n\n"
            . "Sans renouvellement, ce permis sera clôturé automatiquement à l'échéance. Si les travaux se poursuivent, merci de déposer dès maintenant une demande de renouvellement.\n\n"
            . "Cordialement,\nL'équipe TOA",
            $prestataire->getFirstname(),
            $prestataire->getName(),
            $permit->getReference(),
            $permit->getCodeSite(),
            $permit->getType()?->value,
            $dateFinPrevue?->format('d/m/Y H:i') ?? '—',
        );
    }

    private function buildExpirationHtml(PermitTravail $permit, User $prestataire, ?\DateTimeImmutable $dateFinPrevue): string
    {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #e67e22;">Permis de Travail bientôt expiré</h2>
    <p>Bonjour <strong>%s %s</strong>,</p>
    <p>Le Permis de Travail suivant arrive à échéance dans environ 24 heures :</p>
    <table style="border-collapse: collapse; width: 100%%;">
        <tr><td style="padding: 4px 8px;"><strong>Référence</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Site</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Type</strong></td><td style="padding: 4px 8px;">%s</td></tr>
        <tr><td style="padding: 4px 8px;"><strong>Date fin prévue</strong></td><td style="padding: 4px 8px;">%s</td></tr>
    </table>
    <p>Sans renouvellement, ce permis sera clôturé automatiquement à l\'échéance. Si les travaux se poursuivent, merci de déposer dès maintenant une demande de renouvellement.</p>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars((string) $prestataire->getFirstname()),
            htmlspecialchars((string) $prestataire->getName()),
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
            htmlspecialchars($dateFinPrevue?->format('d/m/Y H:i') ?? '—'),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis');
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
            $this->referenceLink($permit),
            htmlspecialchars((string) $permit->getCodeSite()),
            htmlspecialchars((string) $permit->getType()?->value),
            nl2br(htmlspecialchars($commentaire)),
        ) . $this->accessCtaHtml($permit, 'Ouvrir le permis');
    }
}
