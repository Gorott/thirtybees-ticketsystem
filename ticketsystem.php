<?php


use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use TicketSystem\Models\Ticket;
use TicketSystem\Repositories\TicketCategoryRepository;
use TicketSystem\Repositories\TicketStatusRepository;
use TicketSystem\Services\TicketSyncHelper;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

final class TicketSystem extends ModuleCore
{

    public const MODULE_NAME = 'ticketsystem';
    private static ContainerInterface $container;

    public function __construct(private readonly TicketStatusRepository $ticketStatusRepository, private readonly TicketCategoryRepository $ticketCategoryRepository, private readonly TicketSyncHelper $ticketSyncHelper)
    {


        $this->name = 'ticketsystem';
        $this->tab = 'front_office_features';
        $this->version = '1.0';
        $this->author = 'Groot';
        $this->need_instance = false;

        $this->bootstrap = true;

        parent::__construct();


        $this->context->smarty->addPluginsDir(__DIR__ . '/smarty/plugins');

        $this->displayName = 'Ticket System Module';
        $this->description = 'This module adds handling of Ticket system module and replaces the Customer service core';

        $this->buildContainer();

    }


    private function buildContainer(): void
    {
        $cacheFile = _PS_CACHE_DIR_ . 'ticketsystem_container.php';
        $fresh = !is_file($cacheFile) || _PS_MODE_DEV_;

        if (!$fresh) {
            require_once $cacheFile;
            self::$container = new ProjectServiceContainer();
        }

        $container = new ContainerBuilder();
        $context = Context::getContext();

        $container->set(Smarty::class, $context->smarty);
        $container->set(Context::class, $context);
        $context->smarty->addPluginsDir(_PS_MODULE_DIR_ . $this->name . '/smarty/plugins/');

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
        $loader->load('services.yml');
        $container->compile();

        $dumper = new PhpDumper($container);
        file_put_contents($cacheFile, $dumper->dump([
            'class' => 'ProjectServiceContainer'
        ]));

        require_once $cacheFile;
        self::$container = new ProjectServiceContainer;
    }

    public static function getContainer(): ContainerInterface
    {
        return self::$container;
    }

    public function install(): bool
    {
        if (!parent::install() || !self::createTicketStatusTable() || !self::createTicketCategoryTable() || !self::createTicketContactTable() || !self::createTicketTable() || !self::createTicketMessageTable() || !self::createTicketThreadMapTable() || !self::createTicketMessageMapTable()) {
            return false;
        }

        $defaultPromptPath = __DIR__ . '/prompts/default_prompt.txt';
        if (file_exists($defaultPromptPath)) {
            $defaultPrompt = file_get_contents($defaultPromptPath);
            Configuration::updateValue('AI_SUGGESTION_DEFAULT_PROMPT', $defaultPrompt);
        }

        if (!(int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ticket_status`')) {
            $ticketStatus = $this->ticketStatusRepository->create("On Hold", '#BF0F0F');
            $this->ticketStatusRepository->create("Open", '#13ED3A');
            $this->ticketStatusRepository->create("Closed", '#7D7D7D');
            $this->ticketStatusRepository->create("Awaiting Assignee", '#1DA8B8');
            Configuration::updateGlobalValue('DEFAULT_TICKET_STATUS', $ticketStatus->id);
        }


        if (!(int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ticket_category`')) {
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

        $id_tab = Tab::getIdFromClassName('AdminContacts');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->module = $this->name;
            $tab->class_name = 'AdminTicketContacts';
            $tab->save();
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

        $id_tab = (int) Tab::getIdFromClassName('AdminCustomerThreads');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        if (!$this->registerHook('displayCustomerAccount') ||
            !$this->registerHook('actionObjectCustomerThreadDeleteAfter') ||
            !$this->registerHook('actionEmailSend') ||
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->registerHook('actionAdminCustomersView') ||
            !$this->registerHook('displayBackOfficeFooter') ||
            !$this->registerHook('moduleRoutes')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall(): bool
    {
        if (!parent::uninstall() ||
            !$this->unregisterHook('displayCustomerAccount') ||
            !$this->unregisterHook('actionObjectCustomerThreadDeleteAfter') ||
            !$this->unregisterHook('actionEmailSend') ||
            !$this->unregisterHook('displayBackOfficeHeader') ||
            !$this->unregisterHook('actionAdminCustomersView') ||
            !$this->unregisterHook('displayBackOfficeFooter') ||
            !$this->unregisterHook('moduleRoutes')
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

        $idTab = (int) Tab::getIdFromClassName('AdminCustomerThreads');
        if (!$idTab) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = 'AdminCustomerThreads';
            $tab->module = null; // core tab, no module
            $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentCustomer');

            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = 'Customer Service';
            }

            $tab->add();
        }

        $id_tab = Tab::getIdFromClassName('AdminTicketContacts');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->module = null;
            $tab->class_name = 'AdminContacts';
            $tab->save();
        }

        return true;
    }


    public function hookDisplayCustomerAccount()
    {
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/hook/myaccount.tpl');
    }

    public function hookActionObjectCustomerThreadDeleteAfter(array $params): void
    {
        /** @var \CustomerThread $customerThread */
        $customerThread = $params['object'];

        if (!\Validate::isLoadedObject($customerThread)) {
            return;
        }

        $this->ticketSyncHelper->deleteThread($customerThread);
    }

    public function hookActionEmailSend($params)
    {
        $blockedTemplates = ['contact', 'reply_msg'];

        if (!empty($params['template']) && !in_array($params['template'], $blockedTemplates)) {
            return false;
        }
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        if (isset($this->context->controller) && $this->context->controller->controller_type === 'admin') {
            $this->context->controller->addCSS($this->_path.'views/css/admin.css');
        }
    }

    public function hookDisplayBackOfficeFooter($params)
    {
        // Only inject on the AdminCustomers controller
        if (
            !$this->context->controller instanceof AdminCustomersController &&
            !$this->context->controller instanceof AdminOrdersController
        ) {
            return '';
        }

        $id_customer = (int) Tools::getValue('id_customer');
        $id_order = (int) Tools::getValue('id_order');
        $tickets = [];
        if ($id_customer) {
            $tickets = $this->getTicketsByCustomer($id_customer);
        } elseif ($id_order) {
            $tickets = $this->getTicketsByOrder($id_order);
        }

        $this->context->smarty->assign(['tickets' => $tickets]);

        return $this->display(__FILE__, 'views/templates/admin/tickets_wrapper.tpl');
    }

    public function hookModuleRoutes($params)
    {
        return [
            'module-ticketsystem-contact' => [
                'controller' => 'contact',
                'rule'       => 'contact-us',
                'keywords'   => [],
                'params'     => [
                    'fc'     => 'module',
                    'module' => 'ticketsystem',
                    'controller' => 'contact',
                ],
            ],
        ];
    }



    private function getTicketsByCustomer(int $idCustomer): array
    {
        $rows = Db::getInstance()->executeS('
        SELECT id_ticket
        FROM `'._DB_PREFIX_.'ticket`
        WHERE id_customer = '.(int) $idCustomer.'
        ORDER BY last_updated DESC
    ');

        return array_map(fn($row) => new Ticket((int) $row['id_ticket']), $rows);
    }

    private function getTicketsByOrder(int $idOrder): array
    {
        $rows = Db::getInstance()->executeS('
        SELECT id_ticket
        FROM `'._DB_PREFIX_.'ticket`
        WHERE id_order = '.(int) $idOrder.'
        ORDER BY last_updated DESC
    ');

        return array_map(fn($row) => new Ticket((int) $row['id_ticket']), $rows);
    }

    private static function createTicketTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket` (
        `id_ticket` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_customer` INT(11) UNSIGNED DEFAULT NULL,
        `id_ticket_contact` INT(11) UNSIGNED DEFAULT NULL,
        `email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `id_status` INT(11) UNSIGNED NOT NULL,
        `id_category` INT(11) UNSIGNED NOT NULL,
        `id_order` INT(11) UNSIGNED DEFAULT NULL,
        `id_assignee` INT(11) UNSIGNED DEFAULT NULL,
        `last_updated` DATETIME NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id_ticket`),
        KEY `id_customer` (`id_customer`),
        KEY `id_ticket_contact` (`id_ticket_contact`),
        KEY `id_status` (`id_status`),
        KEY `id_category` (`id_category`),
        KEY `id_assignee` (`id_assignee`),
        KEY `id_order` (`id_order`),
        CONSTRAINT `fk_ticket_customer` FOREIGN KEY (`id_customer`)
            REFERENCES `' . _DB_PREFIX_ . 'customer` (`id_customer`) ON DELETE CASCADE,
        CONSTRAINT `fk_ticket_contact` FOREIGN KEY (`id_ticket_contact`)
            REFERENCES `' . _DB_PREFIX_ . 'ticket_contact` (`id_ticket_contact`) ON DELETE SET NULL,
        CONSTRAINT `fk_ticket_assignee` FOREIGN KEY (`id_assignee`)
            REFERENCES `' . _DB_PREFIX_ . 'employee` (`id_employee`) ON DELETE SET NULL,
        CONSTRAINT `fk_ticket_status` FOREIGN KEY (`id_status`)
            REFERENCES `' . _DB_PREFIX_ . 'ticket_status` (`id_ticket_status`) ON DELETE RESTRICT,
        CONSTRAINT `fk_ticket_category` FOREIGN KEY (`id_category`)
            REFERENCES `' . _DB_PREFIX_ . 'ticket_category` (`id_ticket_category`) ON DELETE RESTRICT,
        CONSTRAINT `fk_order` FOREIGN KEY (`id_order`)
            REFERENCES `' . _DB_PREFIX_ . 'orders` (`id_order`) ON DELETE SET NULL
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
        KEY `author_lookup` (`author_type`, `author_id`),
        CONSTRAINT `fk_ticket_message_ticket` FOREIGN KEY (`id_ticket`)
        REFERENCES `' . _DB_PREFIX_ . 'ticket` (`id_ticket`) ON DELETE CASCADE

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
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket_category` (
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
        `id_ticket` INT UNSIGNED NOT NULL,
        `id_customer_thread` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id_ticket`),
        UNIQUE KEY `uniq_customer_thread` (`id_customer_thread`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    private static function createTicketMessageMapTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket_message_map` (
        `id_ticket_message` INT NOT NULL,
        `id_customer_message` INT NOT NULL,
        `synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_ticket_message`),
        UNIQUE KEY `uniq_customer_message` (`id_customer_message`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    private static function createTicketContactTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ticket_contact` (
        `id_ticket_contact` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_category` INT UNSIGNED NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        PRIMARY KEY (`id_ticket_contact`),
        UNIQUE KEY `uniq_category` (`id_category`),
        KEY `idx_ticket_contact_email` (`email`),
        CONSTRAINT `fk_ticket_contact_category`
            FOREIGN KEY (`id_category`)
            REFERENCES `' . _DB_PREFIX_ . 'ticket_category` (`id_ticket_category`)
            ON DELETE CASCADE
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }



}