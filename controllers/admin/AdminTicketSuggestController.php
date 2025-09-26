<?php

use TicketSystem\Enums\MessageAuthor;
use TicketSystem\Models\TicketMessage;
use TicketSystem\Repositories\TicketMessageRepository;
use TicketSystem\Repositories\TicketRepository;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
class AdminTicketSuggestController extends ModuleAdminController
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly TicketMessageRepository $ticketMessageRepository,
    ) {
        parent::__construct();
    }

    public function ajaxProcessGetSuggestion()
    {
        if (!Configuration::get('AI_SUGGESTIONS_ENABLED')) {
            die(json_encode([]));
        }
        header('Content-Type: application/json');

        $ticketId = Tools::getValue('id_ticket');
        $ticket = $this->ticketRepository->findById($ticketId);
        if (!Validate::isLoadedObject($ticket)) {
            die(json_encode([]));
        }

        $orderData = $ticket->id_order ? $ticket->getOrder() : null;

        $contextData = [];
        if (Validate::isLoadedObject($orderData)) {
            $contextData[] = 'Order ID: ' . (int) $orderData->id;

            $orderState = $orderData->getCurrentOrderState();
            if ($orderState && isset($orderState->name[(int) Context::getContext()->language->id])) {
                $contextData[] = 'Order Status: ' . $orderState->name[(int) Context::getContext()->language->id];
            }

            $products = $orderData->getProducts();
            if (!empty($products)) {
                $productNames = array_map(function ($p) {
                    return $p['product_name'];
                }, $products);
                $contextData[] = 'Products: ' . implode(', ', $productNames);
            }
        }

        $messagesHistory = [];

        /**
         * @var TicketMessage $message
         */
        foreach ($this->ticketMessageRepository->findAllByTicketId($ticketId) as $message) {
            $role = MessageAuthor::from($message->author_id);
            switch($role) {
                case MessageAuthor::GUEST:
                case MessageAuthor::CUSTOMER:
                    $messagesHistory[] = 'customer: ' . $message->message;
                    break;
                case MessageAuthor::EMPLOYEE:
                    $messagesHistory[] = 'employee: ' . $message->message;
            }
        }

        $historyString = implode("\n", $messagesHistory);
        $orderContextString = implode("\n", $contextData);
        $draft = Tools::getValue('draft');

        $langIso = 'en'; // fallback
        if ($ticket->id_customer) {
            $customer = new Customer((int) $ticket->id_customer);
            if (Validate::isLoadedObject($customer) && $customer->id_lang) {
                $lang = new Language($customer->id_lang);
                $langIso = $lang->iso_code ?: 'en';
            }
        } elseif ($ticket->id_order) {
            $order = new Order((int) $ticket->id_order);
            if (Validate::isLoadedObject($order) && $order->id_lang) {
                $lang = new Language($order->id_lang);
                $langIso = $lang->iso_code ?: 'en';
            }
        }

        $draft = Tools::getValue('draft');
        $prompt = "Here is the conversation so far:\n" . $historyString;
        if ($orderContextString) {
            $prompt .= "\n\nOrder context:\n" . $orderContextString;
        }
        $prompt .= "\n\nCustomer language: " . $langIso . ". Always respond in this language unless the customer switches to another language.";

        $prompt .= "\n\nDraft reply:\n" . $draft;

        $apiKey = Configuration::get('OPEN_ROUTER_API_KEY');
        $basePrompt = Configuration::get('AI_SUGGESTION_DEFAULT_PROMPT');
        $configPrompt = Configuration::get('AI_SUGGESTION_PROMPT');

        $sysPrompt = $basePrompt . (!empty($configPrompt) ? "\n\nAdditional instructions:\n" . $configPrompt : '');

        $model = 'x-ai/grok-4-fast:free';

        $messages = [
            ['role' => 'system', 'content' => $sysPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => $messages
            ])
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            die(json_encode(['error' => 'cURL Error ' . $error]));
        }

        $data = json_decode($response, true);
        $suggestion = $data['choices'][0]['message']['content'] ?? 'No suggestion available.';
        die(json_encode(['suggestion' => $suggestion]));
    }
}