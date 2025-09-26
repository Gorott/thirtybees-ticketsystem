<?php

namespace TicketSystem\Services;

use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Interfaces\EmployeeRepositoryInterface;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketStatus;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;
use TicketSystem\Repositories\TicketStatusRepository;
use Validate;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketSyncHelper $ticketSyncHelper,
        private readonly TicketMessageRepository $ticketMessageRepository,
    ) {}

    public function createTicket(
        string $subject,
        string $email,
        string $message,
        ?int $order = null,
        ?int $customerId = null,
    ): ?Ticket {
        $ticket = $this->ticketRepository->create($subject, $email, $order, $customerId);
        $this->ticketMessageRepository->create($ticket, $message, $ticket->id_customer != 0 ? MessageAuthor::CUSTOMER : MessageAuthor::GUEST, $email);
        $this->ticketSyncHelper->syncTicketToThread($ticket);

        return $ticket;
    }

    public function assignToEmployee(Ticket $ticket, ?int $employeeId): bool {
        if (!Validate::isLoadedObject($ticket)) {
            return false;
        }

        $ticket->id_assignee = $employeeId;
        $ticket->last_updated = date('Y-m-d H:i:s');
        return $ticket->update();
    }

    public function updateStatus(Ticket $ticket, int $statusId): ?TicketStatus {
        if (!Validate::isLoadedObject($ticket)) {
            return null;
        }

        $ticket->id_status = $statusId;
        $ticket->last_updated = date('Y-m-d H:i:s');

        return $ticket->update() ? new TicketStatus($statusId) : null;
    }
}