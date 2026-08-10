<?php

namespace App\Domain\ActivityPlanning\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PlanningMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private readonly string $fromAddress = 'noreply@toa.app',
    ) {
    }

    public function sendModificationNotification(
        string $recipientEmail,
        string $recipientName,
        int $planningId,
        string $process,
        array $changes,
    ): void {
        if (empty($recipientEmail)) {
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

        $this->mailer->send($email);
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
