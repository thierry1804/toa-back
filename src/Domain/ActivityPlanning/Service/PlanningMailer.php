<?php

namespace App\Domain\ActivityPlanning\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PlanningMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress = 'noreply@toa.app',
    ) {
    }

    public function sendCreationNotification(
        string $recipientEmail,
        string $recipientName,
        int $planningId,
        string $process,
        string $siteCode,
        string $siteName,
    ): void {
        if (empty($recipientEmail)) {
            $this->logger->warning('[Planning] sendCreationNotification: email prestataire manquant', [
                'planningId' => $planningId,
            ]);

            return;
        }

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject(sprintf('[TOA] Nouveau plan d\'activité #%d', $planningId))
            ->text($this->buildCreationTextBody($recipientName, $planningId, $process, $siteCode, $siteName))
            ->html($this->buildCreationHtmlBody($recipientName, $planningId, $process, $siteCode, $siteName));

        try {
            $this->mailer->send($email);
            $this->logger->info('[Planning] Notification de création envoyée', [
                'planningId' => $planningId,
                'recipientEmail' => $recipientEmail,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Planning] Échec envoi notification de création', [
                'planningId' => $planningId,
                'recipientEmail' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendModificationNotification(
        string $recipientEmail,
        string $recipientName,
        int $planningId,
        string $process,
        array $changes,
    ): void {
        if (empty($recipientEmail)) {
            $this->logger->warning('[Planning] sendModificationNotification: email prestataire manquant', [
                'planningId' => $planningId,
            ]);

            return;
        }

        $changeLines = [];
        foreach ($changes as $field => $values) {
            $changeLines[] = sprintf(
                '- %s : "%s" → "%s"',
                $field,
                $values['old'] ?? '—',
                $values['new'] ?? '—'
            );
        }

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($recipientEmail)
            ->subject(sprintf('[TOA] Planning #%d mis à jour', $planningId))
            ->text($this->buildTextBody($recipientName, $planningId, $process, $changeLines))
            ->html($this->buildHtmlBody($recipientName, $planningId, $process, $changeLines));

        try {
            $this->mailer->send($email);
            $this->logger->info('[Planning] Notification de modification envoyée', [
                'planningId' => $planningId,
                'recipientEmail' => $recipientEmail,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Planning] Échec envoi notification de modification', [
                'planningId' => $planningId,
                'recipientEmail' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildCreationTextBody(
        string $recipientName,
        int $planningId,
        string $process,
        string $siteCode,
        string $siteName,
    ): string {
        return sprintf(
            "Bonjour %s,\n\n"
            . "Un nouveau plan d'activité vous a été assigné :\n"
            . "  Planning #%d — %s\n"
            . "  Site : %s (%s)\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipientName,
            $planningId,
            $process,
            $siteName,
            $siteCode
        );
    }

    private function buildCreationHtmlBody(
        string $recipientName,
        int $planningId,
        string $process,
        string $siteCode,
        string $siteName,
    ): string {
        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Nouveau plan d\'activité</h2>
    <p>Bonjour <strong>%s</strong>,</p>
    <p>Un nouveau plan d\'activité vous a été assigné :</p>
    <p><strong>Planning #%d</strong> — %s</p>
    <p>Site : <strong>%s</strong> (%s)</p>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars($recipientName),
            $planningId,
            htmlspecialchars($process),
            htmlspecialchars($siteName),
            htmlspecialchars($siteCode)
        );
    }

    private function buildTextBody(string $recipientName, int $planningId, string $process, array $changeLines): string
    {
        return sprintf(
            "Bonjour %s,\n\n"
            . "Le planning suivant a été mis à jour :\n"
            . "  Planning #%d — %s\n\n"
            . "Modifications :\n%s\n\n"
            . "Cordialement,\nL'équipe TOA",
            $recipientName,
            $planningId,
            $process,
            implode("\n", $changeLines)
        );
    }

    private function buildHtmlBody(string $recipientName, int $planningId, string $process, array $changeLines): string
    {
        $items = '';
        foreach ($changeLines as $line) {
            $items .= sprintf("<li>%s</li>\n", htmlspecialchars($line));
        }

        return sprintf(
            '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Planning mis à jour</h2>
    <p>Bonjour <strong>%s</strong>,</p>
    <p>Le planning suivant a été modifié :</p>
    <p><strong>Planning #%d</strong> — %s</p>
    <h3>Modifications :</h3>
    <ul>%s</ul>
    <hr>
    <p style="color: #666; font-size: 0.9em;">Cordialement,<br>L\'équipe TOA</p>
</body>
</html>',
            htmlspecialchars($recipientName),
            $planningId,
            htmlspecialchars($process),
            $items
        );
    }
}
