<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Service;

final class PaymentSettings
{
    private const OPTION_NAME = 'pbs_settings';

    public function getMode(): string
    {
        $mode = (string) ($this->getAll()['payment_mode'] ?? 'sandbox');

        return $mode === 'live' ? 'live' : 'sandbox';
    }

    public function getCurrency(): string
    {
        $currency = strtolower((string) ($this->getAll()['currency'] ?? 'eur'));

        if (! preg_match('/^[a-z]{3}$/', $currency)) {
            return 'eur';
        }

        return $currency;
    }

    public function getPublishableKey(): string
    {
        $options = $this->getAll();

        if ($this->getMode() === 'live') {
            return trim((string) ($options['stripe_live_publishable_key'] ?? ''));
        }

        return trim((string) ($options['stripe_sandbox_publishable_key'] ?? ''));
    }

    public function getSecretKey(): string
    {
        $options = $this->getAll();

        if ($this->getMode() === 'live') {
            return trim((string) ($options['stripe_live_secret_key'] ?? ''));
        }

        return trim((string) ($options['stripe_sandbox_secret_key'] ?? ''));
    }

    private function getAll(): array
    {
        $raw = get_option(self::OPTION_NAME, []);

        return is_array($raw) ? $raw : [];
    }
}
