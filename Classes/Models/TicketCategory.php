<?php

namespace TicketSystem\Models;

use ObjectModel;

class TicketCategory extends ObjectModel
{
    public $id_ticket_category;
    public $name;
    public $description;
    public $enabled;

    public static $definition = [
        'table' => 'ticket_category',
        'primary' => 'id_ticket_category',
        'fields' => [
            'name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true
            ],
            'description' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true
            ],
            'enabled' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool',
                'required' => true
            ],
        ]
    ];
}