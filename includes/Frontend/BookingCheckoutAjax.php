<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Frontend;

use PropertyBookingSystem\Repository\BookingRepository;
use PropertyBookingSystem\Repository\DiscountRuleRepository;
use PropertyBookingSystem\Repository\SeasonalPricingRepository;
use PropertyBookingSystem\Service\BookingPriceCalculator;
use PropertyBookingSystem\Service\PaymentSettings;

final class BookingCheckoutAjax
{
    private SeasonalPricingRepository $pricingRepository;
    private DiscountRuleRepository $discountRepository;
    private BookingRepository $bookingRepository;
    private BookingPriceCalculator $priceCalculator;
    private PaymentSettings $paymentSettings;

    public function __construct(
        SeasonalPricingRepository $pricingRepository,
        DiscountRuleRepository $discountRepository,
        BookingRepository $bookingRepository,
        BookingPriceCalculator $priceCalculator,
        PaymentSettings $paymentSettings
    ) {
        $this->pricingRepository = $pricingRepository;
        $this->discountRepository = $discountRepository;
        $this->bookingRepository = $bookingRepository;
        $this->priceCalculator = $priceCalculator;
        $this->paymentSettings = $paymentSettings;
    }

    public function register(): void
    {
        add_action('wp_ajax_pbs_create_payment_intent', [$this, 'createPaymentIntent']);
        add_action('wp_ajax_nopriv_pbs_create_payment_intent', [$this, 'createPaymentIntent']);
        add_action('wp_ajax_pbs_confirm_booking', [$this, 'confirmBooking']);
        add_action('wp_ajax_nopriv_pbs_confirm_booking', [$this, 'confirmBooking']);
    }

    public function createPaymentIntent(): void
    {
        if (! check_ajax_referer('pbs_booking_checkout', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid request.', 'property-booking-system')], 400);
        }

        $payload = $this->sanitizePayload($_POST);
        if ($payload === null) {
            wp_send_json_error(['message' => __('Please fill all required fields.', 'property-booking-system')], 400);
        }

        if (! $this->isPropertyAvailable((int) $payload['property_id'], (string) $payload['start_date'], (string) $payload['end_date'])) {
            wp_send_json_error(['message' => __('Selected dates are not available.', 'property-booking-system')], 400);
        }

        $pricing = $this->calculatePricing((int) $payload['property_id'], (string) $payload['start_date'], (string) $payload['end_date']);
        if ($pricing === null) {
            wp_send_json_error(['message' => __('Price is not available for selected dates.', 'property-booking-system')], 400);
        }

        $secretKey = $this->getStripeSecretKey();
        if ($secretKey === '') {
            wp_send_json_error(['message' => __('Stripe is not configured.', 'property-booking-system')], 500);
        }

        $currency = $this->getCurrency();
        $amountInCents = (int) round(((float) $pricing['totalPrice']) * 100);

        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'amount' => (string) $amountInCents,
                'currency' => $currency,
                'automatic_payment_methods[enabled]' => 'true',
                'metadata[property_id]' => (string) $payload['property_id'],
                'metadata[start_date]' => (string) $payload['start_date'],
                'metadata[end_date]' => (string) $payload['end_date'],
                'metadata[email]' => (string) $payload['email'],
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Unable to initiate payment.', 'property-booking-system')], 500);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code > 299 || ! is_array($body) || empty($body['client_secret']) || empty($body['id'])) {
            $message = is_array($body) && isset($body['error']['message']) ? (string) $body['error']['message'] : __('Payment initialization failed.', 'property-booking-system');
            wp_send_json_error(['message' => $message], 400);
        }

        wp_send_json_success([
            'clientSecret' => (string) $body['client_secret'],
            'paymentIntentId' => (string) $body['id'],
            'pricing' => $pricing,
            'currency' => strtoupper($currency),
        ]);
    }

    public function confirmBooking(): void
    {
        if (! check_ajax_referer('pbs_booking_checkout', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid request.', 'property-booking-system')], 400);
        }

        $payload = $this->sanitizePayload($_POST);
        if ($payload === null) {
            wp_send_json_error(['message' => __('Please fill all required fields.', 'property-booking-system')], 400);
        }

        if (! $this->isPropertyAvailable((int) $payload['property_id'], (string) $payload['start_date'], (string) $payload['end_date'])) {
            wp_send_json_error(['message' => __('Selected dates are not available.', 'property-booking-system')], 400);
        }

        $paymentIntentId = sanitize_text_field((string) ($_POST['payment_intent_id'] ?? ''));
        if ($paymentIntentId === '') {
            wp_send_json_error(['message' => __('Missing payment reference.', 'property-booking-system')], 400);
        }

        $pricing = $this->calculatePricing((int) $payload['property_id'], (string) $payload['start_date'], (string) $payload['end_date']);
        if ($pricing === null) {
            wp_send_json_error(['message' => __('Price is not available for selected dates.', 'property-booking-system')], 400);
        }

        $secretKey = $this->getStripeSecretKey();
        if ($secretKey === '') {
            wp_send_json_error(['message' => __('Stripe is not configured.', 'property-booking-system')], 500);
        }

        $intentResponse = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . rawurlencode($paymentIntentId), [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
            ],
        ]);

        if (is_wp_error($intentResponse)) {
            wp_send_json_error(['message' => __('Unable to verify payment.', 'property-booking-system')], 500);
        }

        $code = (int) wp_remote_retrieve_response_code($intentResponse);
        $intent = json_decode((string) wp_remote_retrieve_body($intentResponse), true);

        if ($code < 200 || $code > 299 || ! is_array($intent)) {
            wp_send_json_error(['message' => __('Payment verification failed.', 'property-booking-system')], 400);
        }

        $expectedAmount = (int) round(((float) $pricing['totalPrice']) * 100);
        $intentAmount = isset($intent['amount_received']) ? (int) $intent['amount_received'] : (int) ($intent['amount'] ?? 0);
        $intentStatus = (string) ($intent['status'] ?? '');

        if ($intentStatus !== 'succeeded' || $intentAmount !== $expectedAmount) {
            wp_send_json_error(['message' => __('Payment has not completed successfully.', 'property-booking-system')], 400);
        }

        $chargeId = '';
        if (isset($intent['latest_charge']) && is_string($intent['latest_charge'])) {
            $chargeId = $intent['latest_charge'];
        }

        $paymentMethodId = '';
        if (isset($intent['payment_method']) && is_string($intent['payment_method'])) {
            $paymentMethodId = $intent['payment_method'];
        }

        $created = $this->bookingRepository->create([
            'property_id' => (int) $payload['property_id'],
            'start_date' => (string) $payload['start_date'],
            'end_date' => (string) $payload['end_date'],
            'nights' => (int) $pricing['nights'],
            'base_total' => (float) $pricing['baseTotal'],
            'discount_total' => (float) $pricing['discountAmount'],
            'total_price' => (float) $pricing['totalPrice'],
            'currency' => $this->getCurrency(),
            'first_name' => (string) $payload['first_name'],
            'last_name' => (string) $payload['last_name'],
            'email' => (string) $payload['email'],
            'phone' => (string) $payload['phone'],
            'stripe_payment_intent_id' => $paymentIntentId,
            'stripe_payment_method_id' => $paymentMethodId,
            'stripe_charge_id' => $chargeId,
            'status' => 'paid',
        ]);

        if (! $created) {
            wp_send_json_error(['message' => __('Booking could not be saved.', 'property-booking-system')], 500);
        }

        wp_send_json_success([
            'message' => __('Booking successful. Thank you!', 'property-booking-system'),
        ]);
    }

    private function sanitizePayload(array $input): ?array
    {
        $propertyId = isset($input['property_id']) ? (int) $input['property_id'] : 0;
        $startDate = sanitize_text_field((string) ($input['start_date'] ?? ''));
        $endDate = sanitize_text_field((string) ($input['end_date'] ?? ''));
        $firstName = sanitize_text_field((string) ($input['first_name'] ?? ''));
        $lastName = sanitize_text_field((string) ($input['last_name'] ?? ''));
        $email = sanitize_email((string) ($input['email'] ?? ''));
        $phone = sanitize_text_field((string) ($input['phone'] ?? ''));

        if (
            $propertyId < 1
            || $startDate === ''
            || $endDate === ''
            || $firstName === ''
            || $lastName === ''
            || $email === ''
            || ! is_email($email)
            || $phone === ''
        ) {
            return null;
        }

        return [
            'property_id' => $propertyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    private function calculatePricing(int $propertyId, string $startDate, string $endDate): ?array
    {
        $pricingRows = $this->pricingRepository->getByPropertyId($propertyId);
        $discountRows = $this->discountRepository->getByPropertyId($propertyId);

        return $this->priceCalculator->calculate($startDate, $endDate, $pricingRows, $discountRows);
    }

    private function isPropertyAvailable(int $propertyId, string $startDate, string $endDate): bool
    {
        return ! $this->bookingRepository->hasDateOverlap($propertyId, $startDate, $endDate);
    }

    private function getStripeSecretKey(): string
    {
        return $this->paymentSettings->getSecretKey();
    }

    private function getCurrency(): string
    {
        return $this->paymentSettings->getCurrency();
    }
}
