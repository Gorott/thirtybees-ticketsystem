<?php

namespace TicketSystem\Models;

use ObjectModel;

class TicketMessageMap extends ObjectModel
{
    public $id_ticket_message;

    public $id_customer_message;
    public $synced_at;

    public static $definition = [
        'table' => 'ticket_message_map',
        'primary' => 'id_ticket_message',
        'fields' => [
            'id_ticket_message' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'id_customer_message' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'synced_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'required' => true,
            ],
        ],
    ];
}
