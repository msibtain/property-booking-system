<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Frontend;

use PropertyBookingSystem\Repository\BookingRepository;
use PropertyBookingSystem\Repository\DiscountRuleRepository;
use PropertyBookingSystem\Repository\SeasonalPricingRepository;
use PropertyBookingSystem\Service\PaymentSettings;

final class BookingCalendarShortcode
{
    private SeasonalPricingRepository $pricingRepository;
    private DiscountRuleRepository $discountRepository;
    private BookingRepository $bookingRepository;
    private PaymentSettings $paymentSettings;

    public function __construct(
        SeasonalPricingRepository $pricingRepository,
        DiscountRuleRepository $discountRepository,
        BookingRepository $bookingRepository,
        PaymentSettings $paymentSettings
    ) {
        $this->pricingRepository = $pricingRepository;
        $this->discountRepository = $discountRepository;
        $this->bookingRepository = $bookingRepository;
        $this->paymentSettings = $paymentSettings;
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
            $roomId = isset($post->ID) ? (int) $post->ID : 0;
            $propertyId = (int) get_post_meta($roomId, 'property', true);
            if ($propertyId < 1) {
                return '<p>' . esc_html__('This room is not attached to any property.', 'property-booking-system') . '</p>';
            }
        }

        $pricingRows = $this->pricingRepository->getByPropertyId($propertyId);
        $discountRows = $this->discountRepository->getByPropertyId($propertyId);
        $bookedRanges = $this->bookingRepository->getActiveDateRangesByPropertyId($propertyId);
        $stripePublishableKey = $this->paymentSettings->getPublishableKey();

        $instanceId = 'pbs-booking-calendar-' . wp_generate_uuid4();
        $calendarData = [
            'propertyId' => $propertyId,
            'pricingRows' => $pricingRows,
            'discountRows' => $discountRows,
            'bookedRanges' => $bookedRanges,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pbs_booking_checkout'),
            'stripePublishableKey' => $stripePublishableKey,
            'labels' => [
                'invalidRange' => __('Please select a valid date range.', 'property-booking-system'),
                'missingPrice' => __('Price is not available for one or more selected nights.', 'property-booking-system'),
                'datesUnavailable' => __('Selected dates are not available.', 'property-booking-system'),
                'nights' => __('Nights', 'property-booking-system'),
                'basePrice' => __('Best Price', 'property-booking-system'),
                'discount' => __('Discount', 'property-booking-system'),
                'totalPrice' => __('Total Price', 'property-booking-system'),
                'customerDetails' => __('Customer Details', 'property-booking-system'),
                'firstName' => __('First Name', 'property-booking-system'),
                'lastName' => __('Last Name', 'property-booking-system'),
                'email' => __('Email', 'property-booking-system'),
                'phone' => __('Phone', 'property-booking-system'),
                'paymentDetails' => __('Payment Details', 'property-booking-system'),
                'payNow' => __('Pay Now', 'property-booking-system'),
                'paymentFailed' => __('Payment failed. Please try again.', 'property-booking-system'),
                'bookingSuccess' => __('Booking successful. Thank you!', 'property-booking-system'),
                'stripeNotConfigured' => __('Stripe is not configured.', 'property-booking-system'),
                'working' => __('Processing payment...', 'property-booking-system'),
            ],
        ];

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instanceId); ?>" class="pbs-booking-calendar" data-pbs-calendar="<?php echo esc_attr((string) wp_json_encode($calendarData)); ?>">
            <style>
                @import url('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');
                .pbs-booking-calendar { border: 1px solid #e5e7eb; border-radius: 12px; background: #ffffff; padding: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06); max-width: 760px; }
                .pbs-booking-calendar__fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px; }
                .pbs-booking-calendar__fields p { margin: 0; }
                .pbs-booking-calendar__fields label { display: inline-block; margin-bottom: 6px; font-weight: 600; color: #1f2937; }
                .pbs-booking-calendar__fields input[type="date"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 10px; background: #f8fafc; }
                .pbs-booking-calendar__result { margin-top: 10px; }
                .pbs-booking-calendar__error { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 10px; padding: 10px 12px; font-weight: 500; }
                .pbs-booking-calendar__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
                .pbs-booking-calendar__stat { border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; padding: 10px 12px; }
                .pbs-booking-calendar__stat-label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; margin-bottom: 6px; }
                .pbs-booking-calendar__stat-label i { color: #2563eb; font-size: 14px; }
                .pbs-booking-calendar__stat-value { font-size: 20px; line-height: 1.2; font-weight: 700; color: #0f172a; }
                .pbs-booking-calendar__checkout { margin-top: 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; padding: 14px; }
                .pbs-booking-calendar__checkout h4 { margin: 0 0 10px; font-size: 15px; }
                .pbs-booking-calendar__checkout-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
                .pbs-booking-calendar__checkout-field label { display: block; font-size: 13px; color: #334155; margin-bottom: 4px; }
                .pbs-booking-calendar__checkout-field input, .pbs-booking-calendar__card { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; padding: 9px 10px; font-size: 14px; box-sizing: border-box; }
                .pbs-booking-calendar__card-wrap { margin-top: 10px; }
                .pbs-booking-calendar__action { margin-top: 12px; display: flex; align-items: center; gap: 12px; }
                .pbs-booking-calendar__button { border: 0; border-radius: 8px; background: #2563eb; color: #ffffff; padding: 10px 16px; font-weight: 600; cursor: pointer; }
                .pbs-booking-calendar__button:disabled { opacity: 0.55; cursor: not-allowed; }
                .pbs-booking-calendar__message { font-size: 14px; color: #0f172a; }
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
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (function () {
                var root = document.getElementById(<?php echo wp_json_encode($instanceId); ?>);
                if (!root) return;

                var rawData = root.getAttribute('data-pbs-calendar') || '{}';
                var data = {};
                try { data = JSON.parse(rawData); } catch (err) { return; }

                var startInput = root.querySelector('.pbs-booking-calendar__start');
                var endInput = root.querySelector('.pbs-booking-calendar__end');
                var resultEl = root.querySelector('.pbs-booking-calendar__result');
                var pricingRows = Array.isArray(data.pricingRows) ? data.pricingRows : [];
                var discountRows = Array.isArray(data.discountRows) ? data.discountRows : [];
                var bookedRanges = Array.isArray(data.bookedRanges) ? data.bookedRanges : [];
                var labels = data.labels || {};
                var stripePublishableKey = String(data.stripePublishableKey || '');
                var propertyId = parseInt(data.propertyId || 0, 10);
                var checkoutState = null;

                function normalizeDate(value) { return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : ''; }
                function toDate(dateString) { return new Date(dateString + 'T00:00:00'); }
                function formatMoney(value) { return Number(value || 0).toFixed(2); }

                function getNightlyPrice(dateString) {
                    for (var i = 0; i < pricingRows.length; i++) {
                        var row = pricingRows[i];
                        if (!row || !row.start_date || !row.end_date) continue;
                        if (dateString >= row.start_date && dateString <= row.end_date) return parseFloat(row.price || 0);
                    }
                    return null;
                }

                function getApplicableDiscount(nights) {
                    var selectedRule = null;
                    for (var i = 0; i < discountRows.length; i++) {
                        var rule = discountRows[i];
                        var minNights = parseInt(rule.min_nights, 10);
                        if (Number.isNaN(minNights) || minNights < 1) continue;
                        if (nights >= minNights) selectedRule = rule;
                    }
                    return selectedRule;
                }

                function hasDateOverlap(startDate, endDate) {
                    for (var i = 0; i < bookedRanges.length; i++) {
                        var row = bookedRanges[i];
                        if (!row || !row.start_date || !row.end_date) continue;
                        if (String(row.start_date) < endDate && String(row.end_date) > startDate) return true;
                    }
                    return false;
                }

                function calculateRange(startDate, endDate) {
                    var start = toDate(startDate);
                    var end = toDate(endDate);
                    var totalNights = Math.round((end.getTime() - start.getTime()) / 86400000);
                    if (totalNights <= 0) return { error: labels.invalidRange || 'Please select a valid date range.' };
                    if (hasDateOverlap(startDate, endDate)) return { error: labels.datesUnavailable || 'Selected dates are not available.' };

                    var cursor = new Date(start.getTime());
                    var baseTotal = 0;
                    while (cursor < end) {
                        var isoDate = cursor.toISOString().slice(0, 10);
                        var nightlyPrice = getNightlyPrice(isoDate);
                        if (nightlyPrice === null) return { error: labels.missingPrice || 'Price is not available for one or more selected nights.' };
                        baseTotal += nightlyPrice;
                        cursor.setDate(cursor.getDate() + 1);
                    }

                    var rule = getApplicableDiscount(totalNights);
                    var discountAmount = 0;
                    if (rule) {
                        var discountType = String(rule.discount_type || 'percentage');
                        var discountValue = parseFloat(rule.discount_value || 0);
                        if (discountValue > 0) {
                            discountAmount = discountType === 'fixed' ? discountValue : (baseTotal * discountValue) / 100;
                        }
                    }

                    return { nights: totalNights, baseTotal: baseTotal, discountAmount: discountAmount, totalPrice: Math.max(baseTotal - discountAmount, 0) };
                }

                function getFieldValue(selector) {
                    var field = root.querySelector(selector);
                    return field ? String(field.value || '').trim() : '';
                }

                function submitCheckout(button, messageEl) {
                    if (!checkoutState) return;
                    var firstName = getFieldValue('.pbs-first-name');
                    var lastName = getFieldValue('.pbs-last-name');
                    var email = getFieldValue('.pbs-email');
                    var phone = getFieldValue('.pbs-phone');
                    if (!firstName || !lastName || !email || !phone) {
                        messageEl.textContent = labels.paymentFailed || 'Payment failed. Please try again.';
                        return;
                    }

                    button.disabled = true;
                    messageEl.textContent = labels.working || 'Processing payment...';

                    var body = new URLSearchParams();
                    body.set('action', 'pbs_create_payment_intent');
                    body.set('nonce', String(data.nonce || ''));
                    body.set('property_id', String(propertyId));
                    body.set('start_date', checkoutState.startDate);
                    body.set('end_date', checkoutState.endDate);
                    body.set('first_name', firstName);
                    body.set('last_name', lastName);
                    body.set('email', email);
                    body.set('phone', phone);

                    fetch(String(data.ajaxUrl || ''), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
                        .then(function (response) { return response.json(); })
                        .then(function (json) {
                            if (!json || !json.success || !json.data || !json.data.clientSecret || !json.data.paymentIntentId) {
                                throw new Error(json && json.data && json.data.message ? json.data.message : (labels.paymentFailed || 'Payment failed. Please try again.'));
                            }

                            return checkoutState.stripe.confirmCardPayment(json.data.clientSecret, {
                                payment_method: {
                                    card: checkoutState.card,
                                    billing_details: { name: firstName + ' ' + lastName, email: email, phone: phone }
                                }
                            }).then(function (paymentResult) {
                                if (paymentResult.error) throw new Error(paymentResult.error.message || (labels.paymentFailed || 'Payment failed. Please try again.'));
                                if (!paymentResult.paymentIntent || paymentResult.paymentIntent.status !== 'succeeded') throw new Error(labels.paymentFailed || 'Payment failed. Please try again.');

                                var confirmBody = new URLSearchParams();
                                confirmBody.set('action', 'pbs_confirm_booking');
                                confirmBody.set('nonce', String(data.nonce || ''));
                                confirmBody.set('payment_intent_id', json.data.paymentIntentId);
                                confirmBody.set('property_id', String(propertyId));
                                confirmBody.set('start_date', checkoutState.startDate);
                                confirmBody.set('end_date', checkoutState.endDate);
                                confirmBody.set('first_name', firstName);
                                confirmBody.set('last_name', lastName);
                                confirmBody.set('email', email);
                                confirmBody.set('phone', phone);

                                return fetch(String(data.ajaxUrl || ''), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: confirmBody.toString() });
                            });
                        })
                        .then(function (response) { return response.json(); })
                        .then(function (json) {
                            if (!json || !json.success) throw new Error(json && json.data && json.data.message ? json.data.message : (labels.paymentFailed || 'Payment failed. Please try again.'));
                            messageEl.textContent = (json.data && json.data.message) ? json.data.message : (labels.bookingSuccess || 'Booking successful. Thank you!');
                            button.disabled = true;
                        })
                        .catch(function (error) {
                            messageEl.textContent = error && error.message ? error.message : (labels.paymentFailed || 'Payment failed. Please try again.');
                            button.disabled = false;
                        });
                }

                function initializeCheckout(startDate, endDate, totalPrice) {
                    if (!window.Stripe || !stripePublishableKey) {
                        var messageEl = root.querySelector('.pbs-booking-calendar__message');
                        if (messageEl) messageEl.textContent = labels.stripeNotConfigured || 'Stripe is not configured.';
                        checkoutState = null;
                        return;
                    }

                    var cardContainer = root.querySelector('.pbs-booking-calendar__card');
                    var button = root.querySelector('.pbs-booking-calendar__button');
                    var message = root.querySelector('.pbs-booking-calendar__message');
                    if (!cardContainer || !button || !message) {
                        checkoutState = null;
                        return;
                    }

                    var stripe = window.Stripe(stripePublishableKey);
                    var elements = stripe.elements();
                    var card = elements.create('card');
                    card.mount(cardContainer);

                    checkoutState = { stripe: stripe, card: card, startDate: startDate, endDate: endDate, totalPrice: totalPrice };
                    button.addEventListener('click', function () { submitCheckout(button, message); });
                }

                function renderResult() {
                    if (!startInput || !endInput || !resultEl) return;
                    var startDate = normalizeDate(startInput.value);
                    var endDate = normalizeDate(endInput.value);
                    if (!startDate || !endDate) { resultEl.innerHTML = ''; checkoutState = null; return; }

                    var result = calculateRange(startDate, endDate);
                    if (result.error) {
                        resultEl.innerHTML = '<div class="pbs-booking-calendar__error"><i class="bi bi-exclamation-triangle"></i> ' + result.error + '</div>';
                        checkoutState = null;
                        return;
                    }

                    resultEl.innerHTML = ''
                        + '<div class="pbs-booking-calendar__stats">'
                        + '<div class="pbs-booking-calendar__stat"><div class="pbs-booking-calendar__stat-label"><i class="bi bi-moon-stars"></i> ' + (labels.nights || 'Nights') + '</div><div class="pbs-booking-calendar__stat-value">' + result.nights + '</div></div>'
                        + '<div class="pbs-booking-calendar__stat"><div class="pbs-booking-calendar__stat-label"><i class="bi bi-cash-stack"></i> ' + (labels.basePrice || 'Best Price') + '</div><div class="pbs-booking-calendar__stat-value">' + formatMoney(result.baseTotal) + '</div></div>'
                        + '<div class="pbs-booking-calendar__stat"><div class="pbs-booking-calendar__stat-label"><i class="bi bi-percent"></i> ' + (labels.discount || 'Discount') + '</div><div class="pbs-booking-calendar__stat-value">' + formatMoney(result.discountAmount) + '</div></div>'
                        + '<div class="pbs-booking-calendar__stat"><div class="pbs-booking-calendar__stat-label"><i class="bi bi-receipt"></i> ' + (labels.totalPrice || 'Total Price') + '</div><div class="pbs-booking-calendar__stat-value">' + formatMoney(result.totalPrice) + '</div></div>'
                        + '</div>'
                        + '<div class="pbs-booking-calendar__checkout">'
                        + '<h4>' + (labels.customerDetails || 'Customer Details') + '</h4>'
                        + '<div class="pbs-booking-calendar__checkout-grid">'
                        + '<div class="pbs-booking-calendar__checkout-field"><label>' + (labels.firstName || 'First Name') + '</label><input type="text" class="pbs-first-name" required /></div>'
                        + '<div class="pbs-booking-calendar__checkout-field"><label>' + (labels.lastName || 'Last Name') + '</label><input type="text" class="pbs-last-name" required /></div>'
                        + '<div class="pbs-booking-calendar__checkout-field"><label>' + (labels.email || 'Email') + '</label><input type="email" class="pbs-email" required /></div>'
                        + '<div class="pbs-booking-calendar__checkout-field"><label>' + (labels.phone || 'Phone') + '</label><input type="text" class="pbs-phone" required /></div>'
                        + '</div>'
                        + '<div class="pbs-booking-calendar__card-wrap"><h4>' + (labels.paymentDetails || 'Payment Details') + '</h4><div class="pbs-booking-calendar__card"></div></div>'
                        + '<div class="pbs-booking-calendar__action"><button type="button" class="pbs-booking-calendar__button">' + (labels.payNow || 'Pay Now') + ' (' + formatMoney(result.totalPrice) + ')</button><span class="pbs-booking-calendar__message"></span></div>'
                        + '</div>';

                    initializeCheckout(startDate, endDate, result.totalPrice);
                }

                if (startInput) startInput.addEventListener('change', renderResult);
                if (endInput) endInput.addEventListener('change', renderResult);
            })();
        </script>
        <?php

        return (string) ob_get_clean();
    }
}
