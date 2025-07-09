<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $from,
        private readonly string $to,
    ) {
    }

    public function send(string $subject, string $body, ?string $to = null): void
    {
        $email = (new Email())
            ->from($this->from)
            ->to($to ?? $this->to)
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }
}
