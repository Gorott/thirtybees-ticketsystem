<?php

namespace TicketSystem\Repositories;
use Db;
use TicketSystem\Models\TicketStatus;
use Validate;

class TicketStatusRepository
{
    public function create(string $name, string $color, bool $enabled = true) {
        $ticketStatus = new TicketStatus();
        $ticketStatus->name = $name;
        $ticketStatus->color = $color;
        $ticketStatus->enabled = $enabled;
        $ticketStatus->add();
        return $ticketStatus;
    }
    public function findById(int $id): ?TicketStatus
    {
        $ticketStatus = new TicketStatus($id);
        return Validate::isLoadedObject($ticketStatus) ? $ticketStatus : null;
    }

    /**
     * @return TicketStatus[]
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function findAll(): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'ticket_status`';

        $array = Db::getInstance()->executeS($sql);

        $statuses = [];
        foreach ($array as $ticketStatus) {
            $status = new TicketStatus($ticketStatus['id_ticket_status']);
            if (!Validate::isLoadedObject($status)) {
                continue;
            }

            $statuses[] = $status;
        }

        return $statuses;
    }
}