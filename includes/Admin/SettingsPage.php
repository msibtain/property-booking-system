<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Admin;

final class SettingsPage
{
    private const OPTION_GROUP = 'pbs_settings_group';
    private const OPTION_NAME = 'pbs_settings';
    private const PAGE_SLUG = 'pbs-settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('PBS Settings', 'property-booking-system'),
            __('PBS Settings', 'property-booking-system'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->getDefaultSettings(),
            ]
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $options = wp_parse_args(
            get_option(self::OPTION_NAME, []),
            $this->getDefaultSettings()
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PBS Settings', 'property-booking-system'); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="pbs-payment-mode"><?php esc_html_e('Payment Mode', 'property-booking-system'); ?></label>
                        </th>
                        <td>
                            <select id="pbs-payment-mode" name="<?php echo esc_attr(self::OPTION_NAME); ?>[payment_mode]">
                                <option value="sandbox" <?php selected((string) $options['payment_mode'], 'sandbox'); ?>><?php esc_html_e('Sandbox', 'property-booking-system'); ?></option>
                                <option value="live" <?php selected((string) $options['payment_mode'], 'live'); ?>><?php esc_html_e('Live', 'property-booking-system'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pbs-currency"><?php esc_html_e('Currency (3-letter code)', 'property-booking-system'); ?></label>
                        </th>
                        <td>
                            <input id="pbs-currency" name="<?php echo esc_attr(self::OPTION_NAME); ?>[currency]" type="text" class="regular-text" maxlength="3" value="<?php echo esc_attr((string) $options['currency']); ?>" />
                            <p class="description"><?php esc_html_e('Examples: eur, usd, gbp.', 'property-booking-system'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pbs-sandbox-pk"><?php esc_html_e('Stripe Sandbox Publishable Key', 'property-booking-system'); ?></label>
                        </th>
                        <td>
                            <input id="pbs-sandbox-pk" name="<?php echo esc_attr(self::OPTION_NAME); ?>[stripe_sandbox_publishable_key]" type="text" class="regular-text" value="<?php echo esc_attr((string) $options['stripe_sandbox_publishable_key']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pbs-sandbox-sk"><?php esc_html_e('Stripe Sandbox Secret Key', 'property-booking-system'); ?></label>
                        </th>
                        <td>
                            <input id="pbs-sandbox-sk" name="<?php echo esc_attr(self::OPTION_NAME); ?>[stripe_sandbox_secret_key]" type="password" class="regular-text" value="<?php echo esc_attr((string) $options['stripe_sandbox_secret_key']); ?>" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pbs-live-pk"><?php esc_html_e('Stripe Live Publishable Key', 'property-booking-system'); ?></label>
                        </th>
                        <td>
                            <input id="pbs-live-pk" name="<?php echo esc_attr(self::OPTION_NAME); ?>[stripe_live_publishable_key]" type="text" class="regular-text" value="<?php echo esc_attr((string) $options['stripe_live_publishable_key']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pbs-live-sk"><?php esc_html_e('Stripe Live Secret Key', 'property-booking-system'); ?></label>
                        </th>
                        <td>
                            <input id="pbs-live-sk" name="<?php echo esc_attr(self::OPTION_NAME); ?>[stripe_live_secret_key]" type="password" class="regular-text" value="<?php echo esc_attr((string) $options['stripe_live_secret_key']); ?>" autocomplete="off" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'property-booking-system')); ?>
            </form>
        </div>
        <?php
    }

    public function sanitizeSettings($value): array
    {
        if (! is_array($value)) {
            return $this->getDefaultSettings();
        }

        $paymentMode = sanitize_text_field((string) ($value['payment_mode'] ?? 'sandbox'));
        if (! in_array($paymentMode, ['sandbox', 'live'], true)) {
            $paymentMode = 'sandbox';
        }

        $currency = strtolower(sanitize_text_field((string) ($value['currency'] ?? 'eur')));
        if (! preg_match('/^[a-z]{3}$/', $currency)) {
            $currency = 'eur';
        }

        return [
            'payment_mode' => $paymentMode,
            'currency' => $currency,
            'stripe_sandbox_publishable_key' => sanitize_text_field((string) ($value['stripe_sandbox_publishable_key'] ?? '')),
            'stripe_sandbox_secret_key' => sanitize_text_field((string) ($value['stripe_sandbox_secret_key'] ?? '')),
            'stripe_live_publishable_key' => sanitize_text_field((string) ($value['stripe_live_publishable_key'] ?? '')),
            'stripe_live_secret_key' => sanitize_text_field((string) ($value['stripe_live_secret_key'] ?? '')),
        ];
    }

    private function getDefaultSettings(): array
    {
        return [
            'payment_mode' => 'sandbox',
            'currency' => 'eur',
            'stripe_sandbox_publishable_key' => '',
            'stripe_sandbox_secret_key' => '',
            'stripe_live_publishable_key' => '',
            'stripe_live_secret_key' => '',
        ];
    }
}
