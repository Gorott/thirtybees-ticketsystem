<?php

namespace TicketSystem\Repositories;

use Cache;

use Db;
use Employee;
use Validate;

class EmployeeRepository
{


    public function findByEmail(string $email): ?Employee
    {
        $sql = 'SELECT id_employee FROM ' . _DB_PREFIX_ . 'employee 
                WHERE email = "' . pSQL($email) . '" AND active = 1';

        $employeeId = Db::getInstance()->getValue($sql);

        if (!$employeeId) {
            return null;
        }

        $employee = new Employee($employeeId);
        return Validate::isLoadedObject($employee) ? $employee : null;

    }

    public function findAll(): array {

        if (Cache::isStored('all_employees')) {
            return Cache::retrieve('all_employees');
        }
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'employee`';

        $array = Db::getInstance()->executeS($sql);

        $employees = [];
        foreach ($array as $employee) {
            $employee = new Employee($employee['id_employee']);
            if (!Validate::isLoadedObject($employee)) {
                continue;
            }

            $employees[] = $employee;
        }

        Cache::store('all_employees', $employees);

        return $employees;
    }
}