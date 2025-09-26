<?php

use TicketSystem\Services\TicketService;

class TicketSystemContactModuleFrontController extends ModuleFrontController
{

    public function __construct(
        private readonly TicketService $ticketService,
    ) {
        parent::__construct();

        $this->display_column_left = false;
    }

    public function initContent()
    {
        parent::initContent();

        $orders = Order::getCustomerOrders((int) $this->context->customer->id);
        $this->context->smarty->assign([
            'customer' => $this->context->customer,
            'orders' => $orders
        ]);

        $this->setTemplate('contact.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitTicket')) {
            $subject = Tools::getValue('subject');
            $email = Tools::getValue('email');
            $idOrder = Tools::getValue('id_order');
            $message = Tools::getValue('message');

            $this->ticketService->createTicket(
                $subject,
                $email,
                $message,
                !empty($idOrder) ? (int) $idOrder : null,
                $this->context->customer->id ?: null
            );

            Tools::redirect(
                $this->context->link->getModuleLink('ticketsystem','contact',['submitted'=>1])
            );
        }
    }
}