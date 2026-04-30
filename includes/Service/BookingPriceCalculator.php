<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Service;

final class BookingPriceCalculator
{
    public function calculate(string $startDate, string $endDate, array $pricingRows, array $discountRows): ?array
    {
        if (! $this->isValidDate($startDate) || ! $this->isValidDate($endDate)) {
            return null;
        }

        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        if ($end <= $start) {
            return null;
        }

        $totalNights = (int) $start->diff($end)->days;
        $cursor = $start;
        $baseTotal = 0.0;

        while ($cursor < $end) {
            $isoDate = $cursor->format('Y-m-d');
            $nightlyPrice = $this->getNightlyPrice($isoDate, $pricingRows);
            if ($nightlyPrice === null) {
                return null;
            }

            $baseTotal += $nightlyPrice;
            $cursor = $cursor->modify('+1 day');
        }

        $rule = $this->getApplicableDiscount($totalNights, $discountRows);
        $discountAmount = 0.0;

        if ($rule !== null) {
            $discountType = (string) ($rule['discount_type'] ?? 'percentage');
            $discountValue = isset($rule['discount_value']) ? (float) $rule['discount_value'] : 0.0;

            if ($discountValue > 0) {
                if ($discountType === 'fixed') {
                    $discountAmount = $discountValue;
                } else {
                    $discountAmount = ($baseTotal * $discountValue) / 100;
                }
            }
        }

        $totalPrice = max($baseTotal - $discountAmount, 0.0);

        return [
            'nights' => $totalNights,
            'baseTotal' => $baseTotal,
            'discountAmount' => $discountAmount,
            'totalPrice' => $totalPrice,
        ];
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function getNightlyPrice(string $dateString, array $pricingRows): ?float
    {
        foreach ($pricingRows as $row) {
            if (! isset($row['start_date'], $row['end_date'])) {
                continue;
            }

            if ($dateString >= (string) $row['start_date'] && $dateString <= (string) $row['end_date']) {
                return (float) ($row['price'] ?? 0);
            }
        }

        return null;
    }

    private function getApplicableDiscount(int $nights, array $discountRows): ?array
    {
        $selectedRule = null;

        foreach ($discountRows as $rule) {
            $minNights = isset($rule['min_nights']) ? (int) $rule['min_nights'] : 0;
            if ($minNights < 1) {
                continue;
            }

            if ($nights >= $minNights) {
                $selectedRule = $rule;
            }
        }

        return is_array($selectedRule) ? $selectedRule : null;
    }
}
