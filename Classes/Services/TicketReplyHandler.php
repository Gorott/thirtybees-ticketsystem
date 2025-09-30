<?php

namespace TicketSystem\Services;

use Employee;
use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;

class TicketReplyHandler
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketMessageRepository $ticketMessageRepository,
    ) {

    }

    public function handle(int $ticketId, string $message, Employee $employee): bool
    {
        if ($ticketId <= 0 || empty(trim($message))) {
            return false;
        }

        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket) {
            return false;
        }

        $this->ticketMessageRepository->create($ticket, $message, MessageAuthor::EMPLOYEE, $employee->email);

        $ticket->last_updated = date('Y-m-d H:i:s');

        if ($ticket->id_assignee === null) {
            $ticket->id_assignee = $employee->id;
        }

        $ticket->update();

        return true;
    }
}