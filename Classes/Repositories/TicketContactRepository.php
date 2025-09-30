<?php

namespace TicketSystem\Repositories;

use Cache;
use Employee;
use TicketSystem\Models\TicketContact;
use Validate;

class TicketContactRepository
{
    public function getAllContacts() {
        if (Cache::isStored('all_contacts')) {
            return Cache::retrieve('all_contacts');
        }
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'ticket_contact`';

        $array = Db::getInstance()->executeS($sql);

        $contacts = [];
        foreach ($array as $contact) {
            $contact = new TicketContact($contact['id_ticket_contact']);
            if (!Validate::isLoadedObject($contact)) {
                continue;
            }

            $contacts[] = $contact;
        }

        Cache::store('all_contacts', $contacts);

        return $contacts;
    }
}