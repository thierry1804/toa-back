<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Send a test email to verify SMTP configuration',
)]
class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::OPTIONAL, 'Recipient email', 'perline@yopmail.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $to  = $input->getArgument('to');
        $dsn = $_ENV['MAILER_DSN'] ?? '';

        preg_match('/@([^:@\/]+):(\d+)/', $dsn, $m);
        $host = $m[1] ?? 'webmail.etechconsulting-mg.com';
        $port = $m[2] ?? '587';

        $io->info(sprintf('Sending test email to %s ...', $to));

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to($to)
            ->subject('[TOA] Test de configuration SMTP')
            ->htmlTemplate('email/plan_prevention/test.html.twig')
            ->context([
                'host' => $host,
                'port' => $port,
                'from' => $this->fromAddress,
                'to'   => $to,
            ]);

        try {
            $this->mailer->send($email);
            $io->success(sprintf('Email sent successfully to %s', $to));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to send email: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
