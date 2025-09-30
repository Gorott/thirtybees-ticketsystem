<?php

class AdminTicketContactsController extends AdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'ticket_category';
        $this->className = 'TicketSystem\\Models\\TicketCategory';
        $this->identifier = 'id_ticket_category';
        $this->lang = false;

        $this->_defaultOrderBy = 'id_ticket_contact';
        $this->_defaultOrderWay = 'ASC';

        parent::__construct();

        $this->_select = 'tc.id_ticket_contact, tc.email';
        $this->_join = '
    LEFT JOIN '._DB_PREFIX_.'ticket_contact tc 
        ON tc.id_category = a.id_ticket_category';

        $this->fields_list = [
            'id_ticket_contact' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'name' => [
                'title' => $this->l('Category'),
            ],
            'email' => [
                'title' => $this->l('Assigned Email'),
                'align' => 'left',
                'callback' => 'printEmail',
            ],
        ];


        $this->actions = ['edit'];
    }



    public function renderForm()
    {
        $id_category = (int)Tools::getValue('id_ticket_category');

        // Fetch the current email for this category
        $email = Db::getInstance()->getValue('
            SELECT email FROM '._DB_PREFIX_.'ticket_contact
            WHERE id_category = '.(int)$id_category
        );

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Assign Email to Category'),
                'icon'  => 'icon-envelope',
            ],
            'input' => [
                [
                    'type'  => 'text',
                    'label' => $this->l('Email'),
                    'name'  => 'email',
                    'required' => false,
                    'desc' => $this->l('Leave blank to unassign.'),
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        $this->fields_value['email'] = $email;

        return parent::renderForm();
    }

    public function processSave()
    {
        $id_category = (int)Tools::getValue('id_ticket_category');
        $email = trim(Tools::getValue('email'));

        if ($email) {
            // Insert or update
            Db::getInstance()->execute('
                INSERT INTO '._DB_PREFIX_.'ticket_contact (id_category, email)
                VALUES ('.(int)$id_category.', "'.pSQL($email).'")
                ON DUPLICATE KEY UPDATE email = "'.pSQL($email).'"
            ');
        } else {
            // Remove assignment
            Db::getInstance()->delete('ticket_contact', 'id_category='.(int)$id_category);
        }

        return parent::processSave();
    }
}
