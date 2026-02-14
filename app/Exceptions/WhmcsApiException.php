<?php

namespace App\Exceptions;

use RuntimeException;

class WhmcsApiException extends RuntimeException
{
    protected string $action;
    protected array $context;

    public function __construct(string $message, string $action = '', array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->action = $action;
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Map known WHMCS errors to user-friendly messages.
     */
    public static function friendlyMessage(string $message, string $action = ''): string
    {
        $map = [
            'Authentication Failed' => 'Unable to communicate with billing system. Please try again later.',
            'Invalid IP' => 'Server configuration error. Please contact support.',
            'Order ID Not Found' => 'The requested order could not be found.',
            'Invoice ID Not Found' => 'The requested invoice could not be found.',
            'Ticket ID Not Found' => 'The requested ticket could not be found.',
            'Domain Not Found' => 'The requested domain could not be found.',
            'Service ID Not Found' => 'The requested service could not be found.',
            'Client ID Not Found' => 'Account not found in billing system.',
            'You do not have permission' => 'You do not have permission to perform this action.',
        ];

        foreach ($map as $key => $friendly) {
            if (stripos($message, $key) !== false) {
                return $friendly;
            }
        }

        // Don't leak raw WHMCS messages that might contain internal info
        if (str_contains(strtolower($message), 'sql') || str_contains(strtolower($message), 'database')) {
            return 'An internal error occurred. Please try again later.';
        }

        return $message;
    }
}
