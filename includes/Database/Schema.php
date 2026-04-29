<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Database;

final class Schema
{
    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $pricingTable = $wpdb->prefix . 'pbs_property_pricing';
        $discountTable = $wpdb->prefix . 'pbs_property_discount_rules';

        $pricingSql = "CREATE TABLE {$pricingTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY date_range (start_date, end_date)
        ) {$charsetCollate};";

        $discountSql = "CREATE TABLE {$discountTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            min_nights INT UNSIGNED NOT NULL,
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
            discount_value DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY min_nights (min_nights)
        ) {$charsetCollate};";

        dbDelta($pricingSql);
        dbDelta($discountSql);
    }
}
