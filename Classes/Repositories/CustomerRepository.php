<?php

namespace TicketSystem\Repositories;

use Customer;
use Db;
use Validate;

class CustomerRepository
{


    public function findById(int $id): ?Customer
    {
        $customer = new Customer($id);
        return Validate::isLoadedObject($customer) ? $customer : null;
    }

    public function findByEmail(string $email): ?Customer
    {
        $sql = 'SELECT id_customer FROM ' . _DB_PREFIX_ . 'customer 
                WHERE email = "' . pSQL($email) . '" AND active = 1';

        $customerId = Db::getInstance()->getValue($sql);

        if (!$customerId) {
            return null;
        }

        $customer = new Customer($customerId);
        return Validate::isLoadedObject($customer) ? $customer : null;

    }
}