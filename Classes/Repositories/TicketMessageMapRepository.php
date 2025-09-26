<?php

namespace TicketSystem\Repositories;

use CustomerMessage;
use Db;
use TicketSystem\Models\TicketMessage;
use TicketSystem\Models\TicketMessageMap;
use Validate;

class TicketMessageMapRepository
{

    public function create(TicketMessage $ticketMessage, CustomerMessage $customerMessage): TicketMessageMap
    {
        $map = new TicketMessageMap();
        $map->id_ticket_message = $ticketMessage->id_ticket_message;
        $map->id_customer_message = $customerMessage->id;
        $map->synced_at = date('Y-m-d H:i:s');

        if ($this->findByTicketMessageId($ticketMessage->id_ticket_message)) {
            $map->update();
        } else {
            $map->add();
        }

        return $map;
    }

    public function findByTicketMessageId(int $ticketMessageId): ?TicketMessageMap
    {
        $map = new TicketMessageMap($ticketMessageId);

        return Validate::isLoadedObject($map) ? $map : null;
    }

    public function findByCustomerMessageId(int $customerMessageId): ?TicketMessageMap
    {
        $row = Db::getInstance()->getRow('
            SELECT * FROM `'._DB_PREFIX_.'ticket_message_map`
            WHERE id_customer_message = '.(int) $customerMessageId
        );

        if (!$row) {
            return null;
        }

        $map = new TicketMessageMap();
        $map->hydrate($row);

        return $map;
    }
}
