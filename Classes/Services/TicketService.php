<?php

namespace TicketSystem\Services;

use TicketSystem\Interfaces\EmployeeRepositoryInterface;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketStatus;
use TicketSystem\Repositories\TicketRepository;
use Validate;

class TicketService
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
    ) {}

    public function createTicket(
        string $subject,
        string $email,
        ?int $customerId = null,
    ): ?Ticket {
        $ticket = $this->ticketRepository->create($subject, $email, $customerId);

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