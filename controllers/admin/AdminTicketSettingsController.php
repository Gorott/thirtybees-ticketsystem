<?php

use TicketSystem\Repositories\TicketCategoryRepository;
use TicketSystem\Repositories\TicketStatusRepository;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
class AdminTicketSettingsController extends ModuleAdminController
{
    public function __construct(
        private readonly TicketStatusRepository $ticketStatusRepository,
        private readonly TicketCategoryRepository $ticketCategoryRepository,
    ) {

        $this->bootstrap = true;
        parent::__construct();


        $statuses = [];
        foreach ($this->ticketStatusRepository->findAll() as $ticketStatus) {
            $statuses[] = [
                'id_option' => $ticketStatus->id,
                'name' => $ticketStatus->name . '||' . $ticketStatus->color
            ];
        }

        $categories = [];
        foreach ($this->ticketCategoryRepository->findAll() as $ticketCategory) {
            $categories[] = [
                'id_option' => $ticketCategory->id,
                'name' => $ticketCategory->name,
            ];
        }


        $this->fields_options = [
            'defaults' => [
                'title' => $this->l('Default Ticket Settings'),
                'fields' => [
                    'DEFAULT_TICKET_STATUS' => [
                        'title' => $this->l('Default Status'),
                        'type' => 'select',
                        'list'  => $statuses,
                        'identifier' => 'id_option',
                        'hint' => $this->l('Default status for new tickets (modifiable when creating a ticket)'),
                    ],
                    'DEFAULT_TICKET_CATEGORY' => [
                        'title' => $this->l('Default Category'),
                        'type' => 'select',
                        'list' => $categories,
                        'identifier' => 'id_option',
                        'hint' => $this->l('Default Category for new tickets (modifiable when creating a ticket)'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ]
            ],
            'imap_settings' => [
                'title'  => $this->l('IMAP Settings'),
                'fields' => [
                    'IMAP_HOST' => [
                        'title'      => $this->l('IMAP Host'),
                        'type'       => 'text',
                        'required'   => true,
                        'validation' => 'isUrl',
                        'hint'       => $this->l('Mail server hostname (e.g. imap.gmail.com)'),
                    ],
                    'IMAP_PORT' => [
                        'title'      => $this->l('IMAP Port'),
                        'type'       => 'text',
                        'required'   => true,
                        'validation' => 'isUnsignedInt',
                        'hint'       => $this->l('Usually 993 for SSL, 143 for non-SSL'),
                    ],
                    'IMAP_ENCRYPTION' => [
                        'title'      => $this->l('Encryption'),
                        'type'       => 'select',
                        'list'       => [
                            ['id_option' => 'ssl', 'name' => 'SSL'],
                            ['id_option' => 'tls', 'name' => 'TLS'],
                            ['id_option' => 'none', 'name' => 'None'],
                        ],
                        'identifier' => 'id_option',
                        'hint'       => $this->l('Encryption type for IMAP connection'),
                    ],
                    'IMAP_USER' => [
                        'title'      => $this->l('Username'),
                        'type'       => 'text',
                        'required'   => true,
                        'validation' => 'isString',
                    ],
                    'IMAP_PASSWORD' => [
                        'title'      => $this->l('Password'),
                        'type'       => 'password',
                        'required'   => true,
                        'validation' => 'isString',
                    ],
                    'IMAP_FOLDER' => [
                        'title'      => $this->l('Folder'),
                        'type'       => 'text',
                        'required'   => false,
                        'validation' => 'isString',
                        'hint'       => $this->l('Mailbox folder to fetch from, usually INBOX'),
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
            'ai_suggestions' => [
                'title' => $this->l('AI Suggestions'),
                'fields' => [
                    'AI_SUGGESTIONS_ENABLED' => [
                        'title' => $this->l('AI Suggestions Enabled'),
                        'type' => 'bool',
                        'required' => true,
                        'cast' => 'intval',
                        'validation' => 'isBool',
                        'hint' => 'Enable AI Suggestions in ticket support for employees'
                    ],
                    'OPEN_ROUTER_API_KEY' => [
                        'title' => $this->l('Open Router API Key'),
                        'type' => 'password',
                        'required' => false,
                        'validation' => 'isString',
                        'hint' => 'Open Router api key for generating AI prompts'
                    ],
                    'AI_SUGGESTION_PROMPT' => [
                        'title' => $this->l('Open Router Prompt'),
                        'type' => 'textarea',
                        'cols' => 80,
                        'rows' => 5,
                        'required' => false,
                        'validation' => 'isString',
                        'hint' => 'Open Router prompt for generating AI prompt',
                        'desc' => 'Example: You are a professional support agent that is there to assist employees in responding to tickets'
                    ]
                ],
                'submit' => ['title' => $this->l('Save')],
            ]
        ];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS(_PS_MODULE_DIR_ . $this->module->name.'/views/css/select2.css');
        $this->addJS(_PS_MODULE_DIR_ . $this->module->name.'/views/js/select2.js');
        $this->addJS(_PS_MODULE_DIR_ . $this->module->name . '/views/js/status_preview.js');
    }

    public function getOptionFieldsValues($tab = null)
    {
        $values = parent::getOptionFieldsValues($tab);

        // Mask API key if already stored
        $storedKey = Configuration::get('OPEN_ROUTER_API_KEY');
        if (!empty($storedKey)) {
            $values['OPEN_ROUTER_API_KEY'] = '********';
        }

        return $values;
    }

    public function postProcess()
    {
        // Don’t overwrite the API key with ********
        if (Tools::getValue('OPEN_ROUTER_API_KEY') === '********') {
            $_POST['OPEN_ROUTER_API_KEY'] = Configuration::get('OPEN_ROUTER_API_KEY');
        }

        parent::postProcess();
    }
}