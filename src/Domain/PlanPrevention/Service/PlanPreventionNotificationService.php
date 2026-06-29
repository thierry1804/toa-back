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
