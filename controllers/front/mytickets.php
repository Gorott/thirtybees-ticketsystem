<?php

use TicketSystem\Repositories\TicketRepository;

class TicketSystemMyTicketsModuleFrontController extends ModuleFrontController
{

    public function __construct(
        private readonly TicketRepository $ticketRepository,
    ) {
        parent::__construct();
    }

    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();

        $tickets = $this->ticketRepository->findAllByCustomer($this->context->customer);

        $this->context->smarty->assign([
            'tickets' => $tickets,
        ]);
        $this->setTemplate('mytickets.tpl');

    }
}