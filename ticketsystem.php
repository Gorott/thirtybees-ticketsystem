<?php


use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Repositories\TicketCategoryRepository;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketStatusRepository;
use TicketSystem\Services\TicketService;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';
final class TicketSystem extends ModuleCore
{
    private readonly TicketStatusRepository $ticketStatusRepository;
    private readonly TicketCategoryRepository $ticketCategoryRepository;

    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketMessageRepository $ticketMessageRepository,
    )
    {
        $this->ticketStatusRepository = new TicketStatusRepository();
        $this->ticketCategoryRepository = new TicketCategoryRepository();

        $this->name = 'ticketsystem';
        $this->tab = 'front_office_features';
        $this->version = '1.0';
        $this->author = 'Groot';
        $this->need_instance = false;

        $this->bootstrap = true;

        parent::__construct();


        $this->context->smarty->addPluginsDir(__DIR__.'/smarty/plugins');

        $this->displayName = 'Ticket System Module';
        $this->description = 'This module adds handling of Ticket system module and replaces the Customer service core';

    }

    public function install(): bool
    {
        if (
            !parent::install() ||
            !self::createTicketStatusTable() ||
            !self::createTicketCategoryTable() ||
            !self::createTicketTable() ||
            !self::createTicketMessageTable() ||
            !self::createTicketThreadMapTable()
        ) {
            return false;
        }

        if (!(int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'ticket_status`')) {
            $ticketStatus = $this->ticketStatusRepository->create("On Hold", '#BF0F0F');
            $this->ticketStatusRepository->create("Open", '#13ED3A');
            $this->ticketStatusRepository->create("Closed", '#7D7D7D');
            $this->ticketStatusRepository->create("Awaiting Assignee", '#1DA8B8');
            Configuration::updateGlobalValue('DEFAULT_TICKET_STATUS', $ticketStatus->id);
        }


        if (!(int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'ticket_category`')) {
            $ticketCategory = $this->ticketCategoryRepository->create("Customer Service", 'All Questions');
            Configuration::updateGlobalValue('DEFAULT_TICKET_CATEGORY', $ticketCategory->id);
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketSystem');
        if (!$id_tab) {
            $ticketSystemTab = new Tab();
            $ticketSystemTab->active = 1;
            $ticketSystemTab->class_name = "AdminTicketSystem"; // controller name
            $ticketSystemTab->module = $this->name;
            $ticketSystemTab->id_parent = (int) Tab::getIdFromClassName('AdminParentCustomer');
            foreach (Language::getLanguages(true) as $lang) {
                $ticketSystemTab->name[$lang['id_lang']] = 'Tickets';
            }
            $ticketSystemTab->add();
        } else {
            $ticketSystemTab = new Tab($id_tab);
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketSettings');
        if (!$id_tab) {
            $ticketSettingsTab = new Tab();
            $ticketSettingsTab->active = 1;
            $ticketSettingsTab->class_name = "AdminTicketSettings";
            $ticketSettingsTab->module = $this->name;
            $ticketSettingsTab->id_parent = (int) Tab::getIdFromClassName('AdminParentPreferences');
            foreach (Language::getLanguages(true) as $lang) {
                $ticketSettingsTab->name[$lang['id_lang']] = 'Ticket Settings';
            }
            $ticketSettingsTab->add();
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketCreate');
        if (!$id_tab) {
            $createTicketTab = new Tab();
            $createTicketTab->active = 1;
            $createTicketTab->class_name = "AdminTicketCreate";
            $createTicketTab->module = $this->name;
            $createTicketTab->id_parent = $ticketSystemTab->id;
            foreach (Language::getLanguages(true) as $lang) {
                $createTicketTab->name[$lang['id_lang']] = 'Create Ticket';
            }
            $createTicketTab->add();
        } else {
            $createTicketTab = new Tab($id_tab);
            if ($createTicketTab->id_parent != $ticketSystemTab->id) {
                $createTicketTab->id_parent = $ticketSystemTab->id;
                $createTicketTab->save();
            }
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketSuggest');
        if (!$id_tab) {
            $tab = new Tab();
            $tab->active = 0;
            $tab->class_name = 'AdminTicketSuggest';
            $tab->name = [];
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = 'Ticket Suggest';
            }
            $tab->id_parent = -1;
            $tab->module = $this->name;
            $tab->add();
        }


        if (!$this->registerHook('displayCustomerAccount')) {
            return false;
        }

        return true;
    }

    public function uninstall(): bool
    {
        if (!parent::uninstall() ||
            !$this->unregisterHook('displayCustomerAccount')
        ) {
            return false;
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketSystem');
        while (!$id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketSuggest');
        if (!$id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketSettings');
        if (!$id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        $id_tab = (int) Tab::getIdFromClassName('AdminTicketCreate');
        if (!$id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
        return true;
    }


    private static function createTicketTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket` (
        `id_ticket` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_customer` INT(11) UNSIGNED DEFAULT NULL,
        `email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `id_status` INT(11) UNSIGNED NOT NULL,
        `id_category` INT(11) UNSIGNED NOT NULL,
        `created_at` DATETIME NOT NULL,
        `id_assignee` INT(11) UNSIGNED DEFAULT NULL,
        `last_updated` DATETIME NOT NULL,
        PRIMARY KEY (`id_ticket`),
        KEY `id_customer` (`id_customer`),
        KEY `id_status` (`id_status`),
        KEY `id_category` (`id_category`),
        KEY `id_assignee` (`id_assignee`),
        CONSTRAINT `fk_ticket_customer` FOREIGN KEY (`id_customer`)
            REFERENCES `' . _DB_PREFIX_ . 'customer` (`id_customer`) ON DELETE CASCADE,
        CONSTRAINT `fk_ticket_assignee` FOREIGN KEY (`id_assignee`)
            REFERENCES `' . _DB_PREFIX_ . 'employee` (`id_employee`) ON DELETE SET NULL,
        CONSTRAINT `fk_ticket_status` FOREIGN KEY (`id_status`)
            REFERENCES `' . _DB_PREFIX_ . 'ticket_status` (`id_ticket_status`) ON DELETE RESTRICT,
        CONSTRAINT `fk_ticket_category` FOREIGN KEY (`id_category`)
            REFERENCES `' . _DB_PREFIX_ . 'ticket_category` (`id_ticket_category`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }


    private static function createTicketMessageTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket_message` (
        `id_ticket_message` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_ticket` INT(11) UNSIGNED NOT NULL,
        `message` TEXT NOT NULL,
        `author_type` TINYINT(1) UNSIGNED NOT NULL,
        `author_id` INT(11) UNSIGNED DEFAULT NULL,
        `email` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_ticket_message`),
        KEY `id_ticket` (`id_ticket`),
        KEY `author_lookup` (`author_type`, `author_id`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    private static function createTicketStatusTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket_status` (
            `id_ticket_status` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(64) NOT NULL,
            `color` VARCHAR(7) DEFAULT \'default\',
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_ticket_status`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    private static function createTicketCategoryTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS`' . _DB_PREFIX_ . 'ticket_category` (
        `id_ticket_category` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(64) NOT NULL,
        `description` TEXT NOT NULL,
        `enabled` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_ticket_category`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    private static function createTicketThreadMapTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket_thread_map` (
        `id_ticket` INT(11) UNSIGNED NOT NULL,
        `id_customer_thread` INT(11) UNSIGNED NOT NULL,
        PRIMARY KEY (`id_ticket`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }


    public function hookDisplayCustomerAccount()
    {
        return $this->context->smarty->fetch(_PS_MODULE_DIR_.$this->name.'/views/templates/hook/myaccount.tpl');
    }







}