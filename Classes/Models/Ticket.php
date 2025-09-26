<?php

namespace TicketSystem\Models;

use Customer;
use Employee;
use ObjectModel;
use Order;
use PHPUnit\Event\EventAlreadyAssignedException;
use TicketSystem\Repositories\CustomerRepository;
use TicketSystem\Repositories\TicketStatusRepository;
use Validate;


class Ticket extends ObjectModel
{
    private static ?CustomerRepository $customerRepository = null;
    private static ?TicketStatusRepository $ticketStatusRepository = null;
    public $id_ticket;
    public $id_customer;
    public $email;
    public $id_assignee;
    public $subject;
    public $id_order;
    public $id_status;
    public $id_category;
    public $created_at;
    public $last_updated;

    public static $definition = [
        'table' => 'ticket',
        'primary' => 'id_ticket',
        'fields' => [
            'subject' => [
                'type' => self::TYPE_STRING,
                'required' => true
            ],
            'id_customer' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
                'allow_null' => true
            ],
            'email' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isEmail',
                'required' => true
            ],
            'id_status' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ],
            'id_category' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ],
            'id_order' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
                'allow_null' => true
            ],
            'id_assignee' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => false,
                'allow_null' => true
            ],
            'last_updated' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'required' => true
            ],
            'created_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'required' => true
            ],
        ]
    ];

    public function getCustomer(): ?Customer
    {
        if (!self::$customerRepository) {
            self::$customerRepository = new CustomerRepository();
        }

        return self::$customerRepository->findById($this->id_customer);
    }

    public function getAssignee(): ?Employee
    {
        return new Employee($this->id_assignee);
    }

    public function getStatus(): ?TicketStatus
    {
        if (!self::$ticketStatusRepository) {
            self::$ticketStatusRepository = new TicketStatusRepository();
        }

        return self::$ticketStatusRepository->findById($this->id_status);
    }

    public function getOrder(): ?Order
    {
        $order = new Order($this->id_order);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    public static function formatValue($value, $type, $withQuotes = true, $purify = false, $allowNull = false)
    {
        if ($allowNull && $value === null) {
            return 'NULL';
        }
        return parent::formatValue($value, $type, $withQuotes, $purify, $allowNull);
    }


}