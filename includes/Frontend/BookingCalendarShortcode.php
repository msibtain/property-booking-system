<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Frontend;

use PropertyBookingSystem\Repository\DiscountRuleRepository;
use PropertyBookingSystem\Repository\SeasonalPricingRepository;

final class BookingCalendarShortcode
{
    private SeasonalPricingRepository $pricingRepository;
    private DiscountRuleRepository $discountRepository;

    public function __construct(
        SeasonalPricingRepository $pricingRepository,
        DiscountRuleRepository $discountRepository
    ) {
        $this->pricingRepository = $pricingRepository;
        $this->discountRepository = $discountRepository;
    }

    public function register(): void
    {
        add_shortcode('pbs-booking-calendar', [$this, 'render']);
    }

    public function render(array $atts): string
    {
        $atts = shortcode_atts(
            [
                'property_id' => '0',
            ],
            $atts,
            'pbs-booking-calendar'
        );

        $propertyId = (int) $atts['property_id'];
        if ($propertyId < 1) {
            global $post;
            $room_id = $post->ID;
            $propertyId = (int) get_post_meta($room_id, 'property', true);
            if ($propertyId < 1) {
                return '<p>' . esc_html__('This room is not attached to any property.', 'property-booking-system') . '</p>';
            }
        }

        $pricingRows = $this->pricingRepository->getByPropertyId($propertyId);
        $discountRows = $this->discountRepository->getByPropertyId($propertyId);

        $instanceId = 'pbs-booking-calendar-' . wp_generate_uuid4();
        $calendarData = [
            'pricingRows' => $pricingRows,
            'discountRows' => $discountRows,
            'labels' => [
                'invalidRange' => __('Please select a valid date range.', 'property-booking-system'),
                'missingPrice' => __('Price is not available for one or more selected nights.', 'property-booking-system'),
                'nights' => __('Nights', 'property-booking-system'),
                'basePrice' => __('Best Price', 'property-booking-system'),
                'discount' => __('Discount', 'property-booking-system'),
                'totalPrice' => __('Total Price', 'property-booking-system'),
            ],
        ];

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instanceId); ?>" class="pbs-booking-calendar" data-pbs-calendar="<?php echo esc_attr((string) wp_json_encode($calendarData)); ?>">
            <style>
                @import url('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');

                .pbs-booking-calendar {
                    border: 1px solid #e5e7eb;
                    border-radius: 12px;
                    background: #ffffff;
                    padding: 16px;
                    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
                    max-width: 760px;
                }

                .pbs-booking-calendar__fields {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 14px;
                    margin-bottom: 14px;
                }

                .pbs-booking-calendar__fields p {
                    margin: 0;
                }

                .pbs-booking-calendar__fields label {
                    display: inline-block;
                    margin-bottom: 6px;
                    font-weight: 600;
                    color: #1f2937;
                }

                .pbs-booking-calendar__fields input[type="date"] {
                    width: 100%;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    padding: 9px 10px;
                    background: #f8fafc;
                }

                .pbs-booking-calendar__result {
                    margin-top: 10px;
                }

                .pbs-booking-calendar__error {
                    border: 1px solid #fecaca;
                    background: #fef2f2;
                    color: #991b1b;
                    border-radius: 10px;
                    padding: 10px 12px;
                    font-weight: 500;
                }

                .pbs-booking-calendar__stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                    gap: 10px;
                }

                .pbs-booking-calendar__stat {
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    background: #f8fafc;
                    padding: 10px 12px;
                }

                .pbs-booking-calendar__stat-label {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 13px;
                    color: #475569;
                    margin-bottom: 6px;
                }

                .pbs-booking-calendar__stat-label i {
                    color: #2563eb;
                    font-size: 14px;
                }

                .pbs-booking-calendar__stat-value {
                    font-size: 20px;
                    line-height: 1.2;
                    font-weight: 700;
                    color: #0f172a;
                }
            </style>
            <div class="pbs-booking-calendar__fields">
                <p>
                    <label for="<?php echo esc_attr($instanceId . '-start'); ?>"><?php esc_html_e('Check In Date', 'property-booking-system'); ?></label><br />
                    <input type="date" id="<?php echo esc_attr($instanceId . '-start'); ?>" class="pbs-booking-calendar__start" />
                </p>
                <p>
                    <label for="<?php echo esc_attr($instanceId . '-end'); ?>"><?php esc_html_e('Check Out Date', 'property-booking-system'); ?></label><br />
                    <input type="date" id="<?php echo esc_attr($instanceId . '-end'); ?>" class="pbs-booking-calendar__end" />
                </p>
            </div>
            <div class="pbs-booking-calendar__result" aria-live="polite"></div>
        </div>
        <script>
            (function () {
                var root = document.getElementById(<?php echo wp_json_encode($instanceId); ?>);
                if (!root) {
                    return;
                }

                var rawData = root.getAttribute('data-pbs-calendar') || '{}';
                var data = {};

                try {
                    data = JSON.parse(rawData);
                } catch (err) {
                    return;
                }

                var startInput = root.querySelector('.pbs-booking-calendar__start');
                var endInput = root.querySelector('.pbs-booking-calendar__end');
                var resultEl = root.querySelector('.pbs-booking-calendar__result');
                var pricingRows = Array.isArray(data.pricingRows) ? data.pricingRows : [];
                var discountRows = Array.isArray(data.discountRows) ? data.discountRows : [];
                var labels = data.labels || {};

                function normalizeDate(value) {
                    return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : '';
                }

                function toDate(dateString) {
                    return new Date(dateString + 'T00:00:00');
                }

                function formatMoney(value) {
                    return Number(value || 0).toFixed(2);
                }

                function getNightlyPrice(dateString) {
                    for (var i = 0; i < pricingRows.length; i++) {
                        var row = pricingRows[i];
                        if (!row || !row.start_date || !row.end_date) {
                            continue;
                        }

                        if (dateString >= row.start_date && dateString <= row.end_date) {
                            return parseFloat(row.price || 0);
                        }
                    }

                    return null;
                }

                function getApplicableDiscount(nights) {
                    var selectedRule = null;

                    for (var i = 0; i < discountRows.length; i++) {
                        var rule = discountRows[i];
                        var minNights = parseInt(rule.min_nights, 10);

                        if (Number.isNaN(minNights) || minNights < 1) {
                            continue;
                        }

                        if (nights >= minNights) {
                            selectedRule = rule;
                        }
                    }

                    return selectedRule;
                }

                function calculateRange(startDate, endDate) {
                    var start = toDate(startDate);
                    var end = toDate(endDate);
                    var totalNights = Math.round((end.getTime() - start.getTime()) / 86400000);

                    if (totalNights <= 0) {
                        return {
                            error: labels.invalidRange || 'Please select a valid date range.'
                        };
                    }

                    var cursor = new Date(start.getTime());
                    var baseTotal = 0;

                    while (cursor < end) {
                        var isoDate = cursor.toISOString().slice(0, 10);
                        var nightlyPrice = getNightlyPrice(isoDate);
                        if (nightlyPrice === null) {
                            return {
                                error: labels.missingPrice || 'Price is not available for one or more selected nights.'
                            };
                        }

                        baseTotal += nightlyPrice;
                        cursor.setDate(cursor.getDate() + 1);
                    }

                    var rule = getApplicableDiscount(totalNights);
                    var discountAmount = 0;

                    if (rule) {
                        var discountType = String(rule.discount_type || 'percentage');
                        var discountValue = parseFloat(rule.discount_value || 0);

                        if (discountValue > 0) {
                            if (discountType === 'fixed') {
                                discountAmount = discountValue;
                            } else {
                                discountAmount = (baseTotal * discountValue) / 100;
                            }
                        }
                    }

                    var totalPrice = Math.max(baseTotal - discountAmount, 0);

                    return {
                        nights: totalNights,
                        baseTotal: baseTotal,
                        discountAmount: discountAmount,
                        totalPrice: totalPrice
                    };
                }

                function renderResult() {
                    if (!startInput || !endInput || !resultEl) {
                        return;
                    }

                    var startDate = normalizeDate(startInput.value);
                    var endDate = normalizeDate(endInput.value);

                    if (!startDate || !endDate) {
                        resultEl.innerHTML = '';
                        return;
                    }

                    var result = calculateRange(startDate, endDate);

                    if (result.error) {
                        resultEl.innerHTML = '<div class="pbs-booking-calendar__error"><i class="bi bi-exclamation-triangle"></i> ' + result.error + '</div>';
                        return;
                    }

                    resultEl.innerHTML = ''
                        + '<div class="pbs-booking-calendar__stats">'
                        + '<div class="pbs-booking-calendar__stat">'
                        + '<div class="pbs-booking-calendar__stat-label"><i class="bi bi-moon-stars"></i> ' + (labels.nights || 'Nights') + '</div>'
                        + '<div class="pbs-booking-calendar__stat-value">' + result.nights + '</div>'
                        + '</div>'
                        + '<div class="pbs-booking-calendar__stat">'
                        + '<div class="pbs-booking-calendar__stat-label"><i class="bi bi-cash-stack"></i> ' + (labels.basePrice || 'Best Price') + '</div>'
                        + '<div class="pbs-booking-calendar__stat-value">' + formatMoney(result.baseTotal) + '</div>'
                        + '</div>'
                        + '<div class="pbs-booking-calendar__stat">'
                        + '<div class="pbs-booking-calendar__stat-label"><i class="bi bi-percent"></i> ' + (labels.discount || 'Discount') + '</div>'
                        + '<div class="pbs-booking-calendar__stat-value">' + formatMoney(result.discountAmount) + '</div>'
                        + '</div>'
                        + '<div class="pbs-booking-calendar__stat">'
                        + '<div class="pbs-booking-calendar__stat-label"><i class="bi bi-receipt"></i> ' + (labels.totalPrice || 'Total Price') + '</div>'
                        + '<div class="pbs-booking-calendar__stat-value">' + formatMoney(result.totalPrice) + '</div>'
                        + '</div>'
                        + '</div>';
                }

                if (startInput) {
                    startInput.addEventListener('change', renderResult);
                }

                if (endInput) {
                    endInput.addEventListener('change', renderResult);
                }
            })();
        </script>
        <?php

        return (string) ob_get_clean();
    }
}
