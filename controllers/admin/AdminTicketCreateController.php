<?php

use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Services\TicketService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
class AdminTicketCreateController extends ModuleAdminController
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketMessageRepository $ticketMessageRepository
    )
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->display = 'edit';
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Create Ticket'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Subject'),
                    'name' => 'subject',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Customer Email'),
                    'name' => 'email',
                    'required' => true,
                    'desc' => "If customer email is registered to a customer account it will automatically connect that account to this ticket"
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Assign to self'),
                    'name' => 'selfAssign',
                    'is_bool' => true,
                    'values'  => [
                        [
                            'id'    => 'selfAssign_on',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ],
                        [
                            'id'    => 'selfAssign_off',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ],
                    ],
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Message'),
                    'name' => 'message',
                    'autoload_rte' => true,
                ],
            ],
            'submit' => [
                'title' => $this->l('Create'),
                'name' => 'submitCreateTicket',
            ],
        ];

        $this->fields_value = [
            'selfAssign' => 1
        ];

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitCreateTicket')) {
            $subject = Tools::getValue('subject');
            $email = Tools::getValue('email');
            $message = Tools::getValue('message');
            $assignToSelf = (bool) Tools::getValue('selfAssign');

            $ticket = $this->ticketService->createTicket($subject, $email);
            if ($assignToSelf) {
                $ticket->id_assignee = $this->context->employee->id;
            }
            if (!empty($message)) {
                $this->ticketMessageRepository->create($ticket, $message, MessageAuthor::EMPLOYEE, $this->context->employee->email);
            }
            $this->confirmations[] = $this->l('Ticket created successfully.');

            $link = $this->context->link->getAdminLink('AdminTicketSystem', true, [
                'viewticket' => 1,
                'id_ticket'  => (int) $ticket->id,
            ]);
            Tools::redirectAdmin($link);
        }

        parent::postProcess();
    }
}
