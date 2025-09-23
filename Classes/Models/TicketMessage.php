<?php

namespace TicketSystem\Models;

use Customer;
use Employee;
use Guest;
use ObjectModel;
use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Repositories\TicketRepository;
use Validate;

class TicketMessage extends ObjectModel
{
    private static ?TicketRepository $ticketRepository = null;
    public $id_ticket_message;
    public $id_ticket;
    public $message;
    public $author_type;
    public $author_id;
    public $email;
    public $created_at;


    public static $definition = [
        'table' => 'ticket_message',
        'primary' => 'id_ticket_message',
        'fields' => [
            'id_ticket' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ],
            'message' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
                'required' => true
            ],
            'author_type' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ],
            'author_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false
            ],
            'email' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isEmail',
                'required' => true
            ],
            'created_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'required' => true
            ]
        ]
    ];

    public function getAuthor(): Employee|Customer|string|null
    {
        switch (MessageAuthor::from($this->author_type)) {
            case MessageAuthor::CUSTOMER:
                $customer = new Customer($this->author_id);
                return Validate::isLoadedObject($customer) ? $customer : null;
            case MessageAuthor::EMPLOYEE:
                $employee = new Employee($this->author_id);
                return Validate::isLoadedObject($employee) ? $employee : null;
            case MessageAuthor::GUEST:
                return $this->email;
        }

        return null;
    }

    public function getTicket(): ?Ticket
    {
        if (!self::$ticketRepository) {
            self::$ticketRepository = new TicketRepository();
        }

        return self::$ticketRepository->findById($this->id_ticket);
    }
}