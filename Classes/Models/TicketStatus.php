<?php

namespace TicketSystem\Models;

use ObjectModel;

class TicketStatus extends ObjectModel
{
    public $id_ticket_status;
    public $name;
    public $color;
    public $enabled;

    public static $definition = [
        'table' => 'ticket_status',
        'primary' => 'id_ticket_status',
        'fields' => [
            'name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 32,
                'required' => true
            ],
            'color' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 7
            ],
            'enabled' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool'
            ],
        ]
    ];
}