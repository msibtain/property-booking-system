<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Admin;

use PropertyBookingSystem\Repository\BookingRepository;

final class BookingsPage
{
    private const PAGE_SLUG = 'pbs-bookings';

    private BookingRepository $bookingRepository;

    public function __construct(BookingRepository $bookingRepository)
    {
        $this->bookingRepository = $bookingRepository;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPage']);
    }

    public function addPage(): void
    {
        add_menu_page(
            __('Bookings', 'property-booking-system'),
            __('Bookings', 'property-booking-system'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-calendar-alt',
            58
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $bookings = $this->bookingRepository->getRecent(200);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bookings', 'property-booking-system'); ?></h1>
            <p><?php esc_html_e('Latest paid bookings from the custom bookings table.', 'property-booking-system'); ?></p>

            <?php if ($bookings === []) : ?>
                <p><?php esc_html_e('No bookings found yet.', 'property-booking-system'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Property', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Guest', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Email', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Phone', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Dates', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Nights', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Total', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Status', 'property-booking-system'); ?></th>
                            <th><?php esc_html_e('Created', 'property-booking-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking) : ?>
                            <?php $this->renderBookingRow($booking); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderBookingRow(array $booking): void
    {
        $propertyId = isset($booking['property_id']) ? (int) $booking['property_id'] : 0;
        $propertyLabel = $this->getPropertyLabel($propertyId);
        $fullName = trim(((string) ($booking['first_name'] ?? '')) . ' ' . ((string) ($booking['last_name'] ?? '')));
        $currency = strtoupper((string) ($booking['currency'] ?? 'EUR'));
        $totalPrice = isset($booking['total_price']) ? (float) $booking['total_price'] : 0.0;
        ?>
        <tr>
            <td><?php echo esc_html((string) ($booking['id'] ?? '')); ?></td>
            <td><?php echo esc_html($propertyLabel); ?></td>
            <td><?php echo esc_html($fullName); ?></td>
            <td><?php echo esc_html((string) ($booking['email'] ?? '')); ?></td>
            <td><?php echo esc_html((string) ($booking['phone'] ?? '')); ?></td>
            <td>
                <?php
                echo esc_html((string) ($booking['start_date'] ?? ''));
                echo ' → ';
                echo esc_html((string) ($booking['end_date'] ?? ''));
                ?>
            </td>
            <td><?php echo esc_html((string) ($booking['nights'] ?? '')); ?></td>
            <td><?php echo esc_html(number_format($totalPrice, 2) . ' ' . $currency); ?></td>
            <td><?php echo esc_html((string) ($booking['status'] ?? '')); ?></td>
            <td><?php echo esc_html((string) ($booking['created_at'] ?? '')); ?></td>
        </tr>
        <?php
    }

    private function getPropertyLabel(int $propertyId): string
    {
        if ($propertyId < 1) {
            return __('Unknown', 'property-booking-system');
        }

        $title = get_the_title($propertyId);
        if (! is_string($title) || $title === '') {
            return sprintf(
                __('Property #%d', 'property-booking-system'),
                $propertyId
            );
        }

        return sprintf(
            '%s (#%d)',
            $title,
            $propertyId
        );
    }
}
