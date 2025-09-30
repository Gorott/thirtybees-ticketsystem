<?php

namespace TicketSystem\Models;

use ObjectModel;
use TicketSystem;
use TicketSystem\Repositories\TicketCategoryRepository;
use Validate;

class TicketContact extends ObjectModel
{
    public $id_ticket_contact;

    public $id_category;

    public $email;

    public static $definition = [
        'table'   => 'ticket_contact',
        'primary' => 'id_ticket_contact',
        'fields'  => [
            'id_category' => [
                'type'     => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'email' => [
                'type'     => self::TYPE_STRING,
                'validate' => 'isEmail',
                'required' => true,
                'size'     => 255,
            ],
        ],
    ];


    public function getCategory(): ?TicketCategory
    {
        /** @var TicketCategoryRepository $repo */
        $repo = TicketSystem::getContainer()->get(TicketCategoryRepository::class);
        $category = $repo->findById($this->id_category);

        if (Validate::isLoadedObject($category)) {
            return $category;
        }

        return null;
    }
}
