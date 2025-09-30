<?php

namespace TicketSystem\Repositories;

use Configuration;
use Customer;
use DateTime;
use Db;
use Employee;
use PrestaShopDatabaseException;
use PrestaShopException;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketCategory;
use TicketSystem\Models\TicketContact;
use TicketSystem\Models\TicketStatus;
use TicketSystem\Services\CustomerResolver;
use Validate;

/**
 * Repository for Ticket persistence.
 */
class TicketRepository
{
    public function __construct(
        private readonly CustomerResolver $customerResolver,
    ) {}



    /**
     * @param string $subject Title of the ticket
     * @param int $customerId Id of the customer in the ticket
     * @param TicketStatus|null $ticketStatus Status of the ticket, defaults to Open
     * @param Employee|null $assignee Employee that is currently assigned to the ticket
     * @param TicketCategory|null $ticketCategory Category the ticket is in, defaults to Uncategorized
     * @return Ticket|null
     */
    public function create(string $subject, string $email, ?int $id_contact, ?int $order = null, ?int $id_customer = null, ?TicketStatus $status = null, ?Employee $assignee = null): ?Ticket
    {


        $customer = $this->customerResolver->resolveCustomer($id_customer, $email);

        $ticket = new Ticket();
        $ticket->subject = pSQL($subject);
        $ticket->id_customer = $customer?->id;
        $ticket->email = $email;
        $ticket->id_order = $order;

        $ticketContact = new TicketContact($id_contact);
        if (Validate::isLoadedObject($ticketContact)) {
            $ticket->id_ticket_contact = $id_contact;
        }

        $ticket->id_status = ($status && Validate::isLoadedObject($status)) ? $status->id : (int) Configuration::get('DEFAULT_TICKET_STATUS');
        $ticket->id_category = Validate::isLoadedObject($ticketContact) ? $ticketContact->getCategory()->id_ticket_category  : (int) Configuration::get('DEFAULT_TICKET_CATEGORY');


        $ticket->created_at = (new DateTime())->format('Y-m-d H:i:s');
        $ticket->last_updated = $ticket->created_at;

        $ticket->id_assignee = ($assignee && Validate::isLoadedObject($assignee)) ? $assignee->id : null;


        if (!$ticket->add()) {
            return null;
        }


        return $ticket;
    }

    /**
     * @throws PrestaShopException
     */
    public function findById(int $id): ?Ticket
    {
        $ticket = new Ticket($id);
        return Validate::isLoadedObject($ticket) ? $ticket : null;
    }

    /**
     * @return Ticket[]
     * @throws PrestaShopDatabaseException
     */
    public function findAll(): array
    {
        $rows = Db::executeS('SELECT id_ticket 
             FROM `' . _DB_PREFIX_ . 'ticket`
             ORDER BY created_at DESC');

        return array_map(fn(array $row) => $this->findById((int) $row['id_ticket']), $rows);
    }

    public function findAllByCustomer(Customer $customer): array
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        $rows = Db::getInstance()->executeS('
        SELECT id_ticket
        FROM `'._DB_PREFIX_.'ticket`
        WHERE id_customer = '.(int) $customer->id.'
        ORDER BY created_at DESC
    ');

        if (!$rows) {
            return [];
        }

        return array_map(
            fn(array $row) => $this->findById((int) $row['id_ticket']),
            $rows
        );
    }
}
