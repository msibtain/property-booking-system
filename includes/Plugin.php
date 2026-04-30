<?php

declare(strict_types=1);

namespace PropertyBookingSystem;

use PropertyBookingSystem\Admin\BookingsPage;
use PropertyBookingSystem\Admin\PropertyPricingMetaBox;
use PropertyBookingSystem\Admin\SettingsPage;
use PropertyBookingSystem\Frontend\BookingCheckoutAjax;
use PropertyBookingSystem\Frontend\BookingCalendarShortcode;
use PropertyBookingSystem\Repository\BookingRepository;
use PropertyBookingSystem\Repository\DiscountRuleRepository;
use PropertyBookingSystem\Repository\SeasonalPricingRepository;
use PropertyBookingSystem\Service\BookingPriceCalculator;
use PropertyBookingSystem\Service\PaymentSettings;

final class Plugin
{
    public function boot(): void
    {
        $pricingRepository = new SeasonalPricingRepository();
        $discountRepository = new DiscountRuleRepository();
        $bookingRepository = new BookingRepository();
        $priceCalculator = new BookingPriceCalculator();
        $paymentSettings = new PaymentSettings();

        $metaBox = new PropertyPricingMetaBox($pricingRepository, $discountRepository);
        $metaBox->register();

        $settingsPage = new SettingsPage();
        $settingsPage->register();

        $bookingsPage = new BookingsPage($bookingRepository);
        $bookingsPage->register();

        $bookingCalendarShortcode = new BookingCalendarShortcode($pricingRepository, $discountRepository, $bookingRepository, $paymentSettings);
        $bookingCalendarShortcode->register();

        $bookingCheckoutAjax = new BookingCheckoutAjax($pricingRepository, $discountRepository, $bookingRepository, $priceCalculator, $paymentSettings);
        $bookingCheckoutAjax->register();
    }
}
