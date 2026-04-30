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
        $bookingsTable = $wpdb->prefix . 'pbs_bookings';

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

        $bookingsSql = "CREATE TABLE {$bookingsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            property_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            nights INT UNSIGNED NOT NULL,
            base_total DECIMAL(10,2) NOT NULL,
            discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_price DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'eur',
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            stripe_payment_intent_id VARCHAR(191) NOT NULL,
            stripe_payment_method_id VARCHAR(191) DEFAULT NULL,
            stripe_charge_id VARCHAR(191) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'paid',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY start_date (start_date),
            KEY end_date (end_date),
            KEY email (email),
            UNIQUE KEY stripe_payment_intent_id (stripe_payment_intent_id)
        ) {$charsetCollate};";

        dbDelta($pricingSql);
        dbDelta($discountSql);
        dbDelta($bookingsSql);
    }
}
