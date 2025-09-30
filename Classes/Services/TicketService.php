<?php

namespace TicketSystem\Services;

use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Models\Ticket;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;
use Validate;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketSyncHelper $ticketSyncHelper,
        private readonly TicketMessageRepository $ticketMessageRepository
    ) { }

    public function createTicket(string $subject, string $email, string $message, ?int $idContact, ?int $order = null, ?int $customerId = null): ?Ticket
    {
        $ticket = $this->ticketRepository->create($subject, $email, $idContact, $order, $customerId);
        $this->sendMessage($ticket, $email, $message);
        $this->ticketSyncHelper->syncTicketToThread($ticket);

        return $ticket;
    }

    public function sendMessage(Ticket $ticket, string $email, string $message, bool $sendMail = true) {
        $this->ticketMessageRepository->create($ticket, $message, $ticket->id_customer != 0 ? MessageAuthor::CUSTOMER : MessageAuthor::GUEST, $email);

        if ($sendMail) {

        }
    }

    public function assignToEmployee(int $ticketId, ?int $employeeId): ?Ticket
    {
        $ticket = new Ticket($ticketId);

        if (!Validate::isLoadedObject($ticket)) {
            return null;
        }

        $ticket->id_assignee = $employeeId;
        $this->touch($ticket);

        return $ticket->update() ? $ticket : null;
    }

    public function updateStatus(int $ticketId, int $statusId): ?Ticket
    {
        $ticket = new Ticket($ticketId);

        if (!Validate::isLoadedObject($ticket)) {
            return null;
        }

        $ticket->id_status = $statusId;
        $this->touch($ticket);

        return $ticket->update() ? $ticket : null;
    }

    private function touch(Ticket $ticket): void
    {
        $ticket->last_updated = date('Y-m-d H:i:s');
    }
}