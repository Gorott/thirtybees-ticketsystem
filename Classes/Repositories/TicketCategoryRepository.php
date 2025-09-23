<?php

namespace TicketSystem\Repositories;
use Db;
use TicketSystem\Models\TicketCategory;
use TicketSystem\Models\TicketStatus;
use Validate;

class TicketCategoryRepository
{
    public function create(string $name, string $description, bool $enabled = true): TicketCategory {
        $ticketCategory = new TicketCategory();
        $ticketCategory->name = $name;
        $ticketCategory->description = $description;
        $ticketCategory->enabled = $enabled;
        $ticketCategory->add();

        return $ticketCategory;
    }
    public function findById(int $id): ?TicketCategory
    {
        $ticketCategory = new TicketCategory($id);
        return Validate::isLoadedObject($ticketCategory) ? $ticketCategory : null;
    }

    public function findAll(): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'ticket_category`';

        $array = Db::getInstance()->executeS($sql);

        $categories = [];
        foreach ($array as $ticketCategory) {
            $category = new TicketCategory($ticketCategory['id_ticket_category']);
            if (!Validate::isLoadedObject($category)) {
                continue;
            }

            $categories[] = $category;
        }

        return $categories;
    }
}