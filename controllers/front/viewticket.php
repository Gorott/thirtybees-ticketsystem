<?php

use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;
use TicketSystem\Repositories\TicketStatusRepository;

class ticketsystemviewticketModuleFrontController extends ModuleFrontController
{

    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketMessageRepository $ticketMessageRepository,
    ) {
        parent::__construct();
    }

    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();

        if (!$this->context->customer->isLogged()) {
            Tools::redirect('index.php?controller=authentication');
        }

        $id_ticket = (int) Tools::getValue('id_ticket');

        if (!$id_ticket) {
            Tools::redirect($this->context->link->getModuleLink('ticketsystem', 'mytickets'));
        }


        $ticket = $this->ticketRepository->findById($id_ticket);

        if (!$ticket || $ticket->id_customer != $this->context->customer->id) {
            Tools::redirect($this->context->link->getModuleLink('ticketsystem', 'mytickets'));
        }

        $messages = $this->ticketMessageRepository->findAllByTicketId($id_ticket);

        $this->context->smarty->assign([
            'ticket' => $ticket,
            'messages' => $messages,
        ]);

        $this->setTemplate('viewticket.tpl');
    }

    public function postProcess() {
        if (Tools::isSubmit('message')) {
            $this->processReply();
        }
    }

    protected function processReply() {
        $ticketId = (int) Tools::getValue('id_ticket');
        $message = Tools::getValue('message');


        if (!$ticketId) {
            Tools::redirect($this->context->link->getModuleLink('ticketsystem', 'mytickets'));
        }

        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket || $ticket->id_customer != $this->context->customer->id) {
            Tools::redirect($this->context->link->getModuleLink('ticketsystem', 'mytickets'));
        }

        if ($ticketId && !empty($message)) {
            $this->ticketMessageRepository->create($ticket, $message, MessageAuthor::CUSTOMER, $this->context->customer->email);

            $ticket->last_updated = date('Y-m-d H:i:s');
            $ticket->update();

            Tools::redirect($this->context->link->getModuleLink(
                'ticketsystem',
                'viewticket',
                ['id_ticket' => $ticketId]
            ));
        } else {
            $this->errors[] = "Message was not sent";
        }
    }
}
