<?php

namespace TicketSystem\Services;

use Configuration;
use Db;
use stdClass;

class ImapMailProcessor
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function process(): array
    {
        $errors = [];

        $host = Configuration::get('IMAP_HOST');
        $port = Configuration::get('IMAP_PORT') ?: 993;
        $encryption = Configuration::get('IMAP_ENCRYPTION');
        $user = Configuration::get('IMAP_USER');
        $password = Configuration::get('IMAP_PASSWORD');
        $folder = Configuration::get('IMAP_FOLDER') ?: 'INBOX';

        $flags = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        $mailboxString = sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);

        $inbox = @imap_open($mailboxString, $user, $password);
        if (!$inbox) {
            return ['IMAP connection failed: ' . imap_last_error()];
        }

        $emails = imap_search($inbox, 'UNSEEN') ?: [];
        foreach ($emails as $email_number) {
            try {
                $header = imap_headerinfo($inbox, $email_number);
                $overview = imap_fetch_overview($inbox, $email_number, 0)[0];

                $from = $header->from[0] ?? null;
                $senderEmail = $from ? strtolower($from->mailbox . '@' . $from->host) : null;

                if (!$senderEmail || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid sender email');
                }

                $rawHeaders = imap_fetchheader($inbox, $email_number);
                $recipientEmail = $this->extractRecipient($rawHeaders, $header);

                $idContact = $this->findTicketContactByEmail($recipientEmail);

                $message = $this->getBody($inbox, $email_number);

                $this->ticketService->createTicket(
                    $overview->subject ?: '(no subject)',
                    $senderEmail,
                    $message,
                    $idContact,
                );

                imap_setflag_full($inbox, $email_number, '\\Seen');

            } catch (\Exception $e) {
                $errors[] = sprintf('Message #%d failed: %s', $email_number, $e->getMessage());
            }
        }

        imap_close($inbox);
        return $errors;
    }

    private function extractRecipient(string $rawHeaders, stdClass $header): ?string
    {
        $patterns = [
            '/Delivered-To:\s*([^\s]+)/i',
            '/X-Original-To:\s*([^\s]+)/i',
            '/Envelope-To:\s*([^\s]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $rawHeaders, $matches)) {
                return strtolower(trim($matches[1], " <>\r\n\t"));
            }
        }

        if (!empty($header->to[0])) {
            return strtolower($header->to[0]->mailbox . '@' . $header->to[0]->host);
        }

        return null;
    }

    private function findTicketContactByEmail(?string $recipientEmail): ?int
    {
        if (!$recipientEmail) {
            return null;
        }
        return (int) Db::getInstance()->getValue('
            SELECT id_ticket_contact 
            FROM '._DB_PREFIX_.'ticket_contact
            WHERE email = "'.pSQL($recipientEmail).'"
        ') ?: null;
    }

    private function getBody($inbox, int $email_number): string
    {
        $body = imap_fetchbody($inbox, $email_number, '1.1');
        if (empty($body)) {
            $body = imap_fetchbody($inbox, $email_number, '1');
        }


        $structure = imap_fetchstructure($inbox, $email_number);
        if (isset($structure->encoding)) {
            if ($structure->encoding == 3) {
                $body = imap_base64($body);
            } elseif ($structure->encoding == 4) {
                $body = imap_qprint($body);
            }
        }

        return trim($body);
    }
}
