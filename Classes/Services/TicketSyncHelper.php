<?php

namespace TicketSystem\Services;

use Context;
use CustomerMessage;
use CustomerThread;
use Db;
use Employee;
use Exception;
use Message;
use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Models\Ticket;
use TicketSystem\Models\TicketMessage;
use TicketSystem\Models\TicketThreadMap;
use TicketSystem\Repositories\TicketMessageMapRepository;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;
use TicketSystem\Repositories\TicketThreadMapRepository;
use Validate;

class TicketSyncHelper
{

    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketMessageRepository $ticketMessageRepository,
        private readonly TicketThreadMapRepository $ticketThreadMapRepository,
        private readonly TicketMessageMapRepository $ticketMessageMapRepository,
    ) {
    }

    public function syncTicketToThread(Ticket $ticket): ?TicketThreadMap {

        $map = $this->ticketThreadMapRepository->findByTicketId($ticket->id);
        if ($map) {
            $this->syncTicketMessages($ticket);
            return $map;
        }

        $customerThread = new CustomerThread();
        $customerThread->id_shop = (int)Context::getContext()->shop->id;
        $customerThread->id_lang = (int)Context::getContext()->language->id;
        $customerThread->id_contact = 0;
        $customerThread->id_customer = $ticket->id_customer;
        $customerThread->email = $ticket->email;
        $customerThread->status = 'open';
        $customerThread->token = substr(md5(uniqid()), 0, 12);
        $customerThread->date_add = $ticket->created_at;
        $customerThread->date_upd = $ticket->last_updated;
        $customerThread->add();

        $map = $this->ticketThreadMapRepository->findByTicketId($ticket->id);
        if ($map) {
            return $map;
        }

        $map = $this->ticketThreadMapRepository->create($ticket, $customerThread);
        $this->syncTicketMessages($ticket);
        return $map;
    }

    public function syncTicketMessages(Ticket $ticket)  {
        $map = $this->ticketThreadMapRepository->findByTicketId($ticket->id);
        if (!$map) {
            return $this->syncTicketToThread($ticket);
        }

        $messages = $this->ticketMessageRepository->findAllByTicketId($ticket->id);

        /** @var TicketMessage $message */
        foreach ($messages as $message) {
            if ($this->ticketMessageMapRepository->findByTicketMessageId($message->id)) {
                continue;
            }
            $customerMessage = new CustomerMessage();
            $customerMessage->id_customer_thread = $map->id_customer_thread;
            $customerMessage->id_employee = MessageAuthor::from($message->author_type) == MessageAuthor::EMPLOYEE ? (int)$message->author_id : 0;
            $customerMessage->message = $message->message;
            $customerMessage->date_add = $message->created_at;
            $customerMessage->private = 0;
            $customerMessage->read = 1; // to avoid notifications for employees.
            $customerMessage->add();

            $this->ticketMessageMapRepository->create($message, $customerMessage);
        }
    }

    public function syncThreadToTicket(CustomerThread $customerThread): ?TicketThreadMap {
        $map = $this->ticketThreadMapRepository->findByCustomerThreadId($customerThread->id);
        if ($map) {
            return $map;
        }
        $messages = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'customer_message` WHERE `id_customer_thread` = '.(int)$customerThread->id);
        $ticket = $this->ticketRepository->create('test', $customerThread->email);

        foreach ($messages as $row) {
            $idCustomerMessage = (int)$row['id_customer_message'];
            $message = new CustomerMessage($idCustomerMessage);
            if (!Validate::isLoadedObject($message)) {
                continue;
            }
            if ($this->ticketMessageMapRepository->findByCustomerMessageId($message->id)) {
                continue;
            }
            $authorType = null;
            $email = '';
            if ($message->id_employee !== 0) {
                $authorType = MessageAuthor::EMPLOYEE;
                $employee = new Employee($message->id_employee);
                if (Validate::isLoadedObject($employee)) {
                    $email = $employee->email;
                }
            } elseif ((int)$ticket->id_customer !== 0) {
                $authorType = MessageAuthor::CUSTOMER;
                $email = $ticket->email;
            } else {
                $authorType = MessageAuthor::GUEST;
                $email = $ticket->email;
            }
            $ticketMessage = $this->ticketMessageRepository->create($ticket, $message->message, $authorType, $email);
            $this->ticketMessageMapRepository->create($ticketMessage, $message);
        }

        return $this->ticketThreadMapRepository->create($ticket, $customerThread);
    }

    public function deleteTicket(Ticket $ticket): void
    {
        $threadMap = $this->ticketThreadMapRepository->findByTicketId($ticket->id);

        Db::getInstance()->execute('START TRANSACTION');
        try {
            if ($threadMap) {
                $messages = $this->ticketMessageRepository->findAllByTicketId($ticket->id);
                foreach ($messages as $message) {
                    $this->ticketMessageMapRepository->findByTicketMessageId($message->id)?->delete();
                }

                $customerThread = new CustomerThread((int)$threadMap->id_customer_thread);
                if (Validate::isLoadedObject($customerThread)) {
                    $customerThread->delete();
                }

                $threadMap->delete();
            }

            $ticket->delete();

            Db::getInstance()->execute('COMMIT');
        } catch (Exception $e) {
            Db::getInstance()->execute('ROLLBACK');
            throw $e;
        }
    }

    public function deleteThread(CustomerThread $thread): void
    {
        $threadMap = $this->ticketThreadMapRepository->findByCustomerThreadId($thread->id);

        Db::getInstance()->execute('START TRANSACTION');
        try {
            if ($threadMap) {
                $messages = $this->ticketMessageRepository->findAllByTicketId($threadMap->id_ticket);
                foreach ($messages as $message) {
                    $this->ticketMessageMapRepository->findByTicketMessageId($message->id)?->delete();
                }

                $ticket = new Ticket((int)$threadMap->id_ticket);
                if (Validate::isLoadedObject($ticket)) {
                    $ticket->delete();
                }

                $threadMap->delete();
            }

            Db::getInstance()->execute('COMMIT');
        } catch (Exception $e) {
            Db::getInstance()->execute('ROLLBACK');
            throw $e;
        }
    }
}