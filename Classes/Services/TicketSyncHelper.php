<?php

namespace TicketSystem\Services;

use Context;
use CustomerMessage;
use CustomerThread;
use Message;
use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketMessage;
use TicketSystem\Models\TicketThreadMap;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;
use TicketSystem\Repositories\TicketThreadMapRepository;

class TicketSyncHelper
{

    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketMessageRepository $ticketMessageRepository,
        private readonly TicketThreadMapRepository $ticketThreadMapRepository,
    ) {
    }

    public function syncTicketToThread(Ticket $ticket): ?TicketThreadMap {

        $map = $this->ticketThreadMapRepository->findByTicketId($ticket->id);
        if ($map) {
            $this->syncTicketMessages($ticket);
            return $map;
        }

        $customerThread = new CustomerThread();
        $customerThread->id_shop = (int)Context::getContext()->shop->id;
        $customerThread->id_lang = (int)Context::getContext()->language->id;
        $customerThread->id_contact = 0;
        $customerThread->id_customer = $ticket->id_customer;
        $customerThread->email = $ticket->email;
        $customerThread->status = 'open'; // as it is impossible to sync correctly with configurable statuses
        $customerThread->token = substr(md5(uniqid()), 0, 12);
        $customerThread->date_add = $ticket->created_at;
        $customerThread->date_upd = $ticket->last_updated;
        $customerThread->add();

        $map = $this->ticketThreadMapRepository->create($ticket, $customerThread);
        $this->syncTicketMessages($ticket);
        return $map;
    }

    public function syncTicketMessages(Ticket $ticket)  {
        $map = $this->ticketThreadMapRepository->findByTicketId($ticket->id);
        if (!$map) {
            return $this->syncTicketToThread($ticket);
        }

        $messages = $this->ticketMessageRepository->findAllByTicketId($ticket->id);

        /** @var TicketMessage $message */
        foreach ($messages as $message) {
            $customerMessage = new CustomerMessage();
            $customerMessage->id_customer_thread = $map->id_customer_thread;
            $customerMessage->id_employee = MessageAuthor::from($message->author_type) == MessageAuthor::EMPLOYEE ? $message->author_id : null;
            $customerMessage->message = $message->message;
            $customerMessage->date_add = $message->created_at;
            $customerMessage->add();
        }
    }
}