<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Repository;

final class DiscountRuleRepository
{
    public function getByPropertyId(int $propertyId): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'pbs_property_discount_rules';
        $sql = $wpdb->prepare(
            "SELECT id, min_nights, discount_type, discount_value
            FROM {$tableName}
            WHERE property_id = %d
            ORDER BY min_nights ASC",
            $propertyId
        );

        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    public function replaceByPropertyId(int $propertyId, array $rows): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'pbs_property_discount_rules';
        $wpdb->delete($tableName, ['property_id' => $propertyId], ['%d']);

        foreach ($rows as $row) {
            $wpdb->insert(
                $tableName,
                [
                    'property_id' => $propertyId,
                    'min_nights' => $row['min_nights'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value'],
                ],
                ['%d', '%d', '%s', '%f']
            );
        }
    }
}
