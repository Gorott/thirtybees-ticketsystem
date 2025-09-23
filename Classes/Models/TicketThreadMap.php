<?php

namespace TicketSystem\Models;

use ObjectModel;

class TicketThreadMap extends ObjectModel
{
    public $id_ticket;
    public $id_customer_thread;

    public static $definition = [
        'table' => 'ticket_thread_map',
        'primary' => 'id_ticket',
        'fields' => [
            'id_ticket' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_customer_thread' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
        ]
    ];
}