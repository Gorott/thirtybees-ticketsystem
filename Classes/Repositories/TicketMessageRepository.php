<?php

namespace TicketSystem\Repositories;

use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Enums\TicketStatus;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketMessage;
use TicketSystem\Services\TicketSyncHelper;
use Validate;

class TicketMessageRepository
{

    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) { }

    public function create(Ticket $ticket, string $message, MessageAuthor $author, string $email, ?int $authorId = null): TicketMessage
    {
        $ticketMessage = new TicketMessage();
        $ticketMessage->id_ticket = $ticket->id;
        $ticketMessage->message = $message;
        $ticketMessage->email = $email;
        $ticketMessage->author_id = $authorId;

        if ($ticketMessage->author_id === null) {
            if ($author === MessageAuthor::CUSTOMER) {
                $customer = $this->customerRepository->findByEmail($email);
                if (Validate::isLoadedObject($customer)) {
                    $ticketMessage->author_id = $customer->id;
                } else {
                    $author = MessageAuthor::GUEST;
                }
            } elseif ($author === MessageAuthor::EMPLOYEE) {
                $employee = $this->employeeRepository->findByEmail($email);
                if (Validate::isLoadedObject($employee)) {
                    $ticketMessage->author_id = $employee->id;
                }
            }
        }
        $ticketMessage->author_type = $author->value;

        $ticketMessage->created_at = date("Y-m-d H:i:s");

        $ticket->last_updated = $ticketMessage->created_at;
        $ticket->update();
        $ticketMessage->add();

        return $ticketMessage;
    }

    public function findById(int $id): ?TicketMessage
    {
        $ticketMessage = new TicketMessage($id);
        return Validate::isLoadedObject($ticketMessage) ? $ticketMessage : null;
    }

    public function findAllByTicketId(int $id): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT id_ticket_message FROM '._DB_PREFIX_.'ticket_message WHERE id_ticket = '.(int) $id.' ORDER BY id_ticket_message ASC'
        );

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = new \TicketSystem\Models\TicketMessage((int) $row['id_ticket_message']);
        }

        return $messages;
    }

}
