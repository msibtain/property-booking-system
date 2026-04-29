<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Repository;

final class SeasonalPricingRepository
{
    public function getByPropertyId(int $propertyId): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'pbs_property_pricing';
        $sql = $wpdb->prepare(
            "SELECT id, start_date, end_date, price
            FROM {$tableName}
            WHERE property_id = %d
            ORDER BY start_date ASC",
            $propertyId
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    public function replaceByPropertyId(int $propertyId, array $rows): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'pbs_property_pricing';
        $wpdb->delete($tableName, ['property_id' => $propertyId], ['%d']);

        foreach ($rows as $row) {
            $wpdb->insert(
                $tableName,
                [
                    'property_id' => $propertyId,
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'price' => $row['price'],
                ],
                ['%d', '%s', '%s', '%f']
            );
        }
    }
}
