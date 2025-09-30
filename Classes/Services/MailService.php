<?php

namespace TicketSystem\Services;

use Context;
use Mail;
use TicketSystem\Models\Ticket;

class MailService
{
    public function getReplyToForTicket(Ticket $ticket) {


        $contact = $ticket->getContact();
        $baseEmail = $contact ? !empty($contact->email) : null;

        if (empty($ticket->email_token)) {
            $ticket->email_token = bin2hex(random_bytes(16));
            $ticket->update();
        }

        return preg_replace('/@/', '+' . $ticket->email_token . '@', $baseEmail, 1);
    }

    public function sendTicketCreatedEmail(Ticket $ticket, string $message)
    {
        $subject = "[Ticket #{$ticket->id_ticket}] {$ticket->subject}";

        return Mail::Send(
            $ticket->id_lang ?? Context::getContext()->language->id,
            `ticket_created`,
            $subject,
            [
                `{ticket_id}` => $ticket->id_ticket,
                `{message}` => $message,
            ],
            $ticket->email,
            null,
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            false,
            null,
            null,
            $this->getReplyToForTicket($ticket)
        );
    }
}