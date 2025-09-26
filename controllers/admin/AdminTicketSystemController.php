<?php


use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Models\Ticket;
use TicketSystem\Repositories\EmployeeRepository;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;
use TicketSystem\Repositories\TicketStatusRepository;
use TicketSystem\Services\TicketService;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class AdminTicketSystemController extends ModuleAdminController
{

    public function __construct(
        private readonly TicketMessageRepository $ticketMessageRepository,
        private readonly TicketStatusRepository $ticketStatusRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly TicketService $ticketService,
        private readonly EmployeeRepository $employeeRepository,
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

        $this->processMailbox();

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

    public function processMailbox()
    {
        $host       = Configuration::get('IMAP_HOST');
        $port       = (int) Configuration::get('IMAP_PORT');
        $encryption = Configuration::get('IMAP_ENCRYPTION');
        $user       = Configuration::get('IMAP_USER');
        $password   = Configuration::get('IMAP_PASSWORD');
        $folder     = Configuration::get('IMAP_FOLDER') ?: 'INBOX';

        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        $mailboxString = sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);

        $inbox = @imap_open($mailboxString, $user, $password);

        if (!$inbox) {
            // instead of throw, add to error stack
            $this->errors[] = $this->l('IMAP connection failed: ') . imap_last_error();
            return;
        }

        $status = imap_status($inbox, $mailboxString, SA_UNSEEN);

        if ($status === false) {
            $this->errors[] = $this->l('IMAP status failed: ') . imap_last_error();
            imap_close($inbox);
            return;
        }

        if ($status->unseen == 0) {
            imap_close($inbox);
            return;
        }

        $emails = imap_search($inbox, 'UNSEEN');
        if ($emails === false) {
            $this->errors[] = $this->l('IMAP search failed: ') . imap_last_error();
            imap_close($inbox);
            return;
        }


        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($inbox, $email_number, 0)[0];
            $message  = imap_fetchbody($inbox, $email_number, 1.1);

            if (empty($message)) {
                $message = imap_fetchbody($inbox, $email_number, 1);
            }

            $from = $overview->from;
            $subject = $overview->subject;


            $addresses = imap_rfc822_parse_adrlist($from, 'default.com');

            $emailAddress = null;
            if ($addresses && isset($addresses[0]->mailbox, $addresses[0]->host)) {
                $emailAddress = $addresses[0]->mailbox . '@' . $addresses[0]->host;
            }

            if (!$emailAddress || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                continue;
            }




            try {
                $ticket = $this->ticketRepository->create($subject, $emailAddress);
                $this->ticketMessageRepository->create($ticket, $message, MessageAuthor::CUSTOMER, $emailAddress);
            } catch (\Exception $e) {
                $this->errors[] = $this->l('Ticket creation failed: ') . $e->getMessage();
            }

            imap_setflag_full($inbox, $email_number, "\\Seen");
        }

        imap_close($inbox);
    }

    public function renderView()
    {
        $id = (int)Tools::getValue($this->identifier);
        $ticket = new Ticket($id);

        $messages = $this->ticketMessageRepository->findAllByTicketId($ticket->id);
        $statuses = $this->ticketStatusRepository->findAll();
        $employees = $this->employeeRepository->findAll();

        if (!Validate::isLoadedObject($ticket)) {
            return $this->displayWarning($this->l('Ticket not found'));
        }



        $tpl = $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_.$this->module->name.'/views/templates/admin/ticket_view.tpl'
        );

        $tpl->assign([
            'ticket' => $ticket,
            'messages' => $messages,
            'statuses' => $statuses,
            'employees' => $employees
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

        if (!$ticketId) {

        }

        $ticket = $this->ticketRepository->findById($ticketId);
        if (!$ticket) {
        }
        if ($ticketId && !empty($message)) {
            $this->ticketMessageRepository->create($ticket, $message, MessageAuthor::EMPLOYEE, $this->context->employee->email);

            $ticket->last_updated = date('Y-m-d H:i:s');
            if ($ticket->id_assignee == null) {
                $ticket->id_assignee = $this->context->employee->id;
            }
            $ticket->update();
            Tools::redirectAdmin(
                $this->context->link->getAdminLink('AdminTicketSystem', true, [
                    'id_ticket' => $ticketId,
                    'viewticket' => 1,
                ])
            );
        } else {
            $this->errors[] = "Message was not sent";
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
        $id_ticket = (int) Tools::getValue('id_ticket');
        $id_employee = (int) Tools::getValue('id_employee');

        $id_employee = $id_employee !== null ? (int)$id_employee : (int)$this->context->employee->id;

        // support unassign
        if ($id_employee === -1) {
            $id_employee = null;
        }
        $ticket = new Ticket($id_ticket);

        if (!$this->ticketService->assignToEmployee($ticket, $id_employee)) {
            die(json_encode([
                'success' => false,
                'error'   => $this->l('Could not assign ticket'),
            ]));
        }

        die(json_encode(['success' => true]));
    }


    public function ajaxProcessUpdateTicketStatus()
    {
        $idTicket = (int) Tools::getValue('id_ticket');
        $idStatus = (int) Tools::getValue('id_status');

        $ticket = new Ticket($idTicket);
        $status = $this->ticketService->updateStatus($ticket, $idStatus);

        if ($status === null) {
            die(json_encode([
                'success' => false,
                'message' => $this->l('Failed to update ticket status'),
            ]));
        }

        die(json_encode([
            'success'    => true,
            'new_status' => $status->name,
            'color'      => $status->color,
            'id_status'  => $status->id,
            'id_ticket'  => $ticket->id,
        ]));
    }
}