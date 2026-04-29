<?php

declare(strict_types=1);

namespace PropertyBookingSystem;

use PropertyBookingSystem\Admin\PropertyPricingMetaBox;
use PropertyBookingSystem\Frontend\BookingCalendarShortcode;
use PropertyBookingSystem\Repository\DiscountRuleRepository;
use PropertyBookingSystem\Repository\SeasonalPricingRepository;

final class Plugin
{
    public function boot(): void
    {
        $pricingRepository = new SeasonalPricingRepository();
        $discountRepository = new DiscountRuleRepository();

        $metaBox = new PropertyPricingMetaBox($pricingRepository, $discountRepository);
        $metaBox->register();

        $bookingCalendarShortcode = new BookingCalendarShortcode($pricingRepository, $discountRepository);
        $bookingCalendarShortcode->register();
    }
}
