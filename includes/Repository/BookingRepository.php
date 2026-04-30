<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Repository;

final class BookingRepository
{
    public function getActiveDateRangesByPropertyId(int $propertyId): array
    {
        global $wpdb;

        if ($propertyId < 1) {
            return [];
        }

        $tableName = $wpdb->prefix . 'pbs_bookings';
        $query = $wpdb->prepare(
            "SELECT start_date, end_date
            FROM {$tableName}
            WHERE property_id = %d
                AND status NOT IN ('cancelled', 'failed', 'refunded')
            ORDER BY start_date ASC, end_date ASC",
            $propertyId
        );

        $rows = $wpdb->get_results($query);
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function ($row): array {
                if (! is_object($row)) {
                    return [];
                }

                return [
                    'start_date' => (string) ($row->start_date ?? ''),
                    'end_date' => (string) ($row->end_date ?? ''),
                ];
            },
            $rows
        )));
    }

    public function hasDateOverlap(int $propertyId, string $startDate, string $endDate): bool
    {
        global $wpdb;

        if ($propertyId < 1 || $startDate === '' || $endDate === '') {
            return false;
        }

        $tableName = $wpdb->prefix . 'pbs_bookings';
        $query = $wpdb->prepare(
            "SELECT id
            FROM {$tableName}
            WHERE property_id = %d
                AND status NOT IN ('cancelled', 'failed', 'refunded')
                AND start_date < %s
                AND end_date > %s
            LIMIT 1",
            $propertyId,
            $endDate,
            $startDate
        );

        $bookingId = $wpdb->get_var($query);

        return $bookingId !== null;
    }

    public function create(array $row): bool
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'pbs_bookings';
        $result = $wpdb->insert(
            $tableName,
            [
                'property_id' => $row['property_id'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'nights' => $row['nights'],
                'base_total' => $row['base_total'],
                'discount_total' => $row['discount_total'],
                'total_price' => $row['total_price'],
                'currency' => $row['currency'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'stripe_payment_intent_id' => $row['stripe_payment_intent_id'],
                'stripe_payment_method_id' => $row['stripe_payment_method_id'],
                'stripe_charge_id' => $row['stripe_charge_id'],
                'status' => $row['status'],
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%f',
                '%f',
                '%f',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return is_int($result) && $result > 0;
    }

    public function getRecent(int $limit = 100): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'pbs_bookings';
        $safeLimit = max(1, min($limit, 500));

        $query = $wpdb->prepare(
            "SELECT id, property_id, start_date, end_date, nights, total_price, currency, first_name, last_name, email, phone, status, created_at
            FROM {$tableName}
            ORDER BY created_at DESC, id DESC
            LIMIT %d",
            $safeLimit
        );

        $rows = $wpdb->get_results($query);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            static function ($row): array {
                return is_object($row) ? get_object_vars($row) : [];
            },
            $rows
        );
    }
}
