<?php
/**
 * Plugin Name: Property Booking System
 * Description: Property Booking System plugin, with custom pricing table and discount rules etc.
 * Version: 1.0.0
 * Author: Property Booking System
 * Text Domain: property-booking-system
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('PBS_PLUGIN_FILE', __FILE__);
define('PBS_PLUGIN_VERSION', '1.0.0');

require_once __DIR__ . '/includes/Autoloader.php';

\PropertyBookingSystem\Autoloader::register();

register_activation_hook(PBS_PLUGIN_FILE, static function (): void {
    \PropertyBookingSystem\Database\Schema::createTables();
});

add_action('plugins_loaded', static function (): void {
    (new \PropertyBookingSystem\Plugin())->boot();
});
