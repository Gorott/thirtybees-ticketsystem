<?php

use TicketSystem\Models\Ticket;
use TicketSystem\Repositories\EmployeeRepository;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketStatusRepository;
use TicketSystem\Services\ImapMailProcessor;
use TicketSystem\Services\TicketReplyHandler;
use TicketSystem\Services\TicketService;

if (!defined('_TB_VERSION_')) {
    exit;
}


class AdminTicketSystemController extends ModuleAdminController
{

    public function __construct(
        private readonly TicketService $ticketService,
        private readonly ImapMailProcessor $mailProcessor,
        private readonly TicketMessageRepository $ticketMessageRepository,
        private readonly TicketStatusRepository $ticketStatusRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly TicketReplyHandler $ticketReplyHandler,
    )
    {
        $this->bootstrap = true;
        $this->show_toolbar = true;
        $this->table = 'ticket';
        $this->className = 'TicketSystem\\Models\\Ticket';

        $this->lang = false;

        parent::__construct();

        $this->_select = '
    CONCAT(c.firstname, " ", c.lastname) AS customer_name,
    s.name AS status_name,
    s.color AS status_color,
    t.name AS category_name,
    IFNULL(CONCAT(e.firstname, " ", e.lastname), "") AS assignee_name
';


        $this->_join = '
    LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (a.id_customer = c.id_customer)
    LEFT JOIN `' . _DB_PREFIX_ . 'ticket_status` s ON (a.id_status = s.id_ticket_status)
    LEFT JOIN `' . _DB_PREFIX_ . 'ticket_category` t ON (a.id_category = t.id_ticket_category)
    LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON (a.id_assignee = e.id_employee)
';


        $this->fields_list = [
            'id_ticket' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'search' => false,
            ],
            'customer_name' => [
                'title' => $this->l('Customer'),
                'callback' => 'renderCustomer',
                'orderBy' => false,
                'search' => false,
            ],
            'email' => [
                'title' => $this->l('Email'),
                'orderBy' => false,
                'search' => false,
            ],
            'subject' => [
                'title' => $this->l('Subject'),
                'filter_key' => 'a!subject',
                'search' => false,
            ],
            'category_name' => [
                'title' => $this->l('Category'),
                'search' => false,
            ],
            'status_name' => [
                'title' => $this->l('Status'),
                'callback' => 'renderStatus',
                'filter_key' => 's!name',
                'search' => false,
            ],
            'assignee_name' => [
                'title' => $this->l('Assignee'),
                'callback' => 'renderAssignee',
                'filter_key' => 'e!id_employee',
                'remove_onclick' => true,
                'search' => false,
            ],
            'last_updated' => [
                'title' => $this->l('Last updated'),
                'type' => 'datetime',
                'search' => false,
            ]
        ];

        $this->errors = array_merge($this->errors, $this->mailProcessor->process());
        $this->addRowAction('view');
        $this->addRowAction('delete');
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);


        $ticketCreateId = Tab::getIdFromClassName('AdminTicketCreate');
        if ($this->context->employee->hasAccess($ticketCreateId, Profile::PERMISSION_EDIT)) {
            $this->page_header_toolbar_btn['new'] = [
                'href' => $this->context->link->getAdminLink('AdminTicketCreate'),
                'icon' => 'process-icon-new',
                'desc' => $this->l('Create Ticket'),
            ];
        }

        $settingsTabId = Tab::getIdFromClassName('AdminTicketSettings');
        if ($this->context->employee->hasAccess($settingsTabId, Profile::PERMISSION_EDIT)) {
            $this->page_header_toolbar_btn['settings'] = [
                'href' => $this->context->link->getAdminLink('AdminTicketSettings'),
                'icon' => 'process-icon-cogs',
                'desc' => $this->l('Settings'),
            ];
        }

    }

    public function renderView()
    {
        $ticket = new Ticket(Tools::getValue('id_ticket'));

        if (!Validate::isLoadedObject($ticket)) {
            return $this->errors[] = $this->l('Ticket not found');
        }

        $tpl = $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . TicketSystem::MODULE_NAME . '/views/templates/admin/ticket_view.tpl'
        );

        $tpl->assign([
            'ticket' => $ticket,
            'messages' => $this->ticketMessageRepository->findAllByTicketId($ticket->id_ticket),
            'statuses' => $this->ticketStatusRepository->findAll(),
            'employees' => $this->employeeRepository->findAll(),
        ]);


        return $tpl->fetch();
    }


    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addJS(_PS_MODULE_DIR_.$this->module->name.'/views/js/admin_status_select.js');
        $this->addJS(_PS_MODULE_DIR_.$this->module->name.'/views/js/admin_ticket_assign.js');
        $this->addJS(_PS_MODULE_DIR_.$this->module->name.'/views/js/ai_suggestions.js');

        Media::addJsDef([
            'adminToken' => $this->token,
            'adminAiSuggest' => Tools::getAdminTokenLite('AdminTicketSuggest'),
            'currentEmployee' => $this->context->employee->id,
        ]);
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit("message")) {
            $this->processReply();
        }
    }

    protected function processReply()
    {
        $ticketId = (int) Tools::getValue('id_ticket');
        $message = Tools::getValue('message');

        if ($this->ticketReplyHandler->handle($ticketId, $message, $this->context->employee)) {
            Tools::redirectAdmin(
                $this->context->link->getAdminLink('AdminTicketSystem', true, [
                    'id_ticket' => $ticketId,
                    'viewTicket' => 1,
                ]),
            );
        } else {
            $this->errors[] = $this->l('There was an error processing your message.');
        }
    }


    public function renderStatus($value, $row)
    {
        return '<span class="label" style="background-color:' . $row['status_color'] . ';color:#fff;">' . $value . '</span>';
    }

    public function renderCustomer($value, $row)
    {
        $url = $this->context->link->getAdminLink('AdminCustomers', true);
        $url .= '&id_customer='.(int)$row['id_customer'].'&viewcustomer';

        return '<a href="'.$url.'">'.htmlspecialchars($value).'</a>';
    }



    public function renderAssignee($value, $row)
    {
        if ($value) {
            return htmlspecialchars($value);
        }

        return '<a href="#" 
              id="assign-to-me" 
              data-ticket-id="' . (int) $row['id_ticket'] . '"
              title="' . $this->l('Assign this ticket to me') . '">' . $this->l('Assign to me') . '</a>';
    }

    public function ajaxProcessAssignToEmployee()
    {
        $idTicket = Tools::getValue('id_ticket');
        $idEmployee = Tools::getValue('id_mployee') !== null
            ? (int)Tools::getValue('id_employee')
            : (int)$this->context->employee->id;

        if ($idEmployee === -1) {
            $idEmployee = null;
        }

        die(json_encode(
            $this->ticketService->assignToEmployee($idTicket, $idEmployee)
        ));
    }


    public function ajaxProcessUpdateTicketStatus()
    {
        $idTicket = Tools::getValue('id_ticket');
        $idStatus = Tools::getValue('id_status');

        die(json_encode(
            $this->ticketService->updateStatus($idTicket, $idStatus)
        ));
    }
}