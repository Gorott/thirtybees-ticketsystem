<?php

namespace TicketSystem\Repositories;

use CustomerThread;
use Db;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketThreadMap;
use Validate;

class TicketThreadMapRepository
{
    public function create(Ticket $ticket, CustomerThread $customerThread) {
        $map = new TicketThreadMap();
        $map->id_ticket = $ticket->id;
        $map->id_customer_thread = $customerThread->id;
        $map->add();
    }
    public function findByTicketId($ticketId) {
        $ticketThreadMap = new TicketThreadMap($ticketId);
        return Validate::isLoadedObject($ticketThreadMap) ? $ticketThreadMap : null;
    }

    public function findByCustomerThreadId($customerThreadId) {
        $row = Db::getInstance()->getRow('
        SELECT * FROM `'._DB_PREFIX_.'tb_ticket_thread_map`
        WHERE id_customer_thread = '.(int)$customerThreadId
        );

        if (!$row) {
            return null;
        }

        $map = new TicketThreadMap();
        $map->hydrate($row);

        return $map;
    }
}