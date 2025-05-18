<?php

namespace WHMCS\Module\Notification\Telegram;

use WHMCS\Config\Setting;
use WHMCS\Exception;
use WHMCS\Module\Notification\DescriptionTrait;
use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Notification\Contracts\NotificationInterface;

class Telegram implements NotificationModuleInterface
{
    use DescriptionTrait;

    public function __construct()
    {
        $this->setDisplayName('Telegram')
        ->setLogoFileName('logo.png');
    }

    public function settings()
    {
        return [
            'botToken' => [
                'FriendlyName' => 'Bot Token',
                'Type' => 'password', // Changed to password for better security
                'Description' => 'Enter your Telegram Bot Token',
                'Placeholder' => 'Example: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
                'Required' => true,
            ],
            'botChatID' => [
                'FriendlyName' => 'Chat ID',
                'Type' => 'text',
                'Description' => 'Enter the Chat ID of the user/channel',
                'Placeholder' => 'Example: -100123456789',
                'Required' => true,
            ],
        ];
    }

    private function sendTelegramRequest($botToken, $botChatID, $message, $parseMode = 'Markdown')
    {
        $url = sprintf(
            'https://api.telegram.org/bot%s/sendMessage',
            rawurlencode($botToken)
        );

        $postData = [
            'chat_id' => $botChatID,
            'text' => $message,
            'parse_mode' => $parseMode,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('Telegram API Error: HTTP ' . $httpCode);
        }

        $decodedResponse = json_decode($response, true);
        if (!$decodedResponse['ok']) {
            throw new Exception('Telegram API Error: ' . ($decodedResponse['description'] ?? 'Unknown error'));
        }

        return $decodedResponse;
    }

    public function testConnection($settings)
    {
        if (empty($settings['botToken']) || empty($settings['botChatID'])) {
            throw new Exception('Bot Token and Chat ID are required');
        }

        return $this->sendTelegramRequest(
            $settings['botToken'],
            $settings['botChatID'],
            "✅ WHMCS Telegram Integration Test Successful"
        );
    }

    public function notificationSettings()
    {
        return [];
    }

    public function getDynamicField($fieldName, $settings)
    {
        return [];
    }

    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings)
    {
        if (empty($moduleSettings['botToken']) || empty($moduleSettings['botChatID'])) {
            throw new Exception('Bot Token and Chat ID are required');
        }

        $messageContent = sprintf(
            "*%s*\n\n%s\n\n[Open »](%s)",
                                  $notification->getTitle(),
                                  $notification->getMessage(),
                                  $notification->getUrl()
        );

        return $this->sendTelegramRequest(
            $moduleSettings['botToken'],
            $moduleSettings['botChatID'],
            $messageContent
        );
    }
}
