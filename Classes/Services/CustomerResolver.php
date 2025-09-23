<?php

namespace TicketSystem\Services;

use Customer;
use TicketSystem\Repositories\CustomerRepository;
use Validate;

class CustomerResolver
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
    ) {}

    public function resolveCustomer(?int $customerId, string $email): ?Customer
    {
        if ($customerId) {
            $customer = new Customer($customerId);
            if (Validate::isLoadedObject($customer)) {
                return $customer;
            }
        }

        return $this->customerRepository->findByEmail($email);
    }
}