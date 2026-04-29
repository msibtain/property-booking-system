<?php

declare(strict_types=1);

namespace PropertyBookingSystem\Admin;

use PropertyBookingSystem\Repository\DiscountRuleRepository;
use PropertyBookingSystem\Repository\SeasonalPricingRepository;

final class PropertyPricingMetaBox
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
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post_property', [$this, 'save'], 10, 1);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'pbs_property_pricing_box',
            __('Property Pricing & Discounts', 'property-booking-system'),
            [$this, 'render'],
            'property',
            'normal',
            'default'
        );
    }

    public function render(\WP_Post $post): void
    {
        $pricingRows = $this->pricingRepository->getByPropertyId((int) $post->ID);
        $discountRows = $this->discountRepository->getByPropertyId((int) $post->ID);

        wp_nonce_field('pbs_property_pricing_save', 'pbs_property_pricing_nonce');
        ?>
        <style>
            .pbs-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            .pbs-table th, .pbs-table td { padding: 8px; border: 1px solid #dcdcde; }
            .pbs-remove-btn { color: #b32d2e; cursor: pointer; }
            .pbs-actions { margin: 8px 0 20px; }
        </style>

        <h3><?php esc_html_e('Seasonal Pricing', 'property-booking-system'); ?></h3>
        <table class="pbs-table" id="pbs-pricing-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Start Date', 'property-booking-system'); ?></th>
                    <th><?php esc_html_e('End Date', 'property-booking-system'); ?></th>
                    <th><?php esc_html_e('Price', 'property-booking-system'); ?></th>
                    <th><?php esc_html_e('Action', 'property-booking-system'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pricingRows === []) : ?>
                    <?php $this->renderPricingRow(0, ['start_date' => '', 'end_date' => '', 'price' => '']); ?>
                <?php else : ?>
                    <?php foreach ($pricingRows as $index => $row) : ?>
                        <?php $this->renderPricingRow((int) $index, $row); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pbs-actions">
            <button type="button" class="button button-secondary" id="pbs-add-pricing-row"><?php esc_html_e('Add Pricing Row', 'property-booking-system'); ?></button>
        </div>

        <h3><?php esc_html_e('Discount Rules', 'property-booking-system'); ?></h3>
        <table class="pbs-table" id="pbs-discount-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Minimum Nights', 'property-booking-system'); ?></th>
                    <th><?php esc_html_e('Discount Type', 'property-booking-system'); ?></th>
                    <th><?php esc_html_e('Discount Value', 'property-booking-system'); ?></th>
                    <th><?php esc_html_e('Action', 'property-booking-system'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($discountRows === []) : ?>
                    <?php $this->renderDiscountRow(0, ['min_nights' => '', 'discount_type' => 'percentage', 'discount_value' => '']); ?>
                <?php else : ?>
                    <?php foreach ($discountRows as $index => $row) : ?>
                        <?php $this->renderDiscountRow((int) $index, $row); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pbs-actions">
            <button type="button" class="button button-secondary" id="pbs-add-discount-row"><?php esc_html_e('Add Discount Row', 'property-booking-system'); ?></button>
        </div>

        <script>
            (function () {
                function attachRemoveHandlers(scope) {
                    scope.querySelectorAll('.pbs-remove-btn').forEach(function (button) {
                        button.addEventListener('click', function () {
                            var row = this.closest('tr');
                            if (row) {
                                row.remove();
                            }
                        });
                    });
                }

                function addRow(tableId, rowHtml) {
                    var tbody = document.querySelector('#' + tableId + ' tbody');
                    if (!tbody) {
                        return;
                    }
                    tbody.insertAdjacentHTML('beforeend', rowHtml);
                    attachRemoveHandlers(tbody);
                }

                document.getElementById('pbs-add-pricing-row').addEventListener('click', function () {
                    var index = document.querySelectorAll('#pbs-pricing-table tbody tr').length;
                    var rowHtml = '<tr>'
                        + '<td><input type="date" name="pbs_pricing_rows[' + index + '][start_date]" value="" /></td>'
                        + '<td><input type="date" name="pbs_pricing_rows[' + index + '][end_date]" value="" /></td>'
                        + '<td><input type="number" step="0.01" min="0" name="pbs_pricing_rows[' + index + '][price]" value="" /></td>'
                        + '<td><button type="button" class="button-link-delete pbs-remove-btn">Remove</button></td>'
                        + '</tr>';
                    addRow('pbs-pricing-table', rowHtml);
                });

                document.getElementById('pbs-add-discount-row').addEventListener('click', function () {
                    var index = document.querySelectorAll('#pbs-discount-table tbody tr').length;
                    var rowHtml = '<tr>'
                        + '<td><input type="number" min="1" name="pbs_discount_rows[' + index + '][min_nights]" value="" /></td>'
                        + '<td><select name="pbs_discount_rows[' + index + '][discount_type]"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></td>'
                        + '<td><input type="number" step="0.01" min="0" name="pbs_discount_rows[' + index + '][discount_value]" value="" /></td>'
                        + '<td><button type="button" class="button-link-delete pbs-remove-btn">Remove</button></td>'
                        + '</tr>';
                    addRow('pbs-discount-table', rowHtml);
                });

                attachRemoveHandlers(document);
            })();
        </script>
        <?php
    }

    public function save(int $postId): void
    {
        if (! isset($_POST['pbs_property_pricing_nonce'])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pbs_property_pricing_nonce'])), 'pbs_property_pricing_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $pricingRows = $this->sanitizePricingRows($_POST['pbs_pricing_rows'] ?? []);
        $discountRows = $this->sanitizeDiscountRows($_POST['pbs_discount_rows'] ?? []);

        $this->pricingRepository->replaceByPropertyId($postId, $pricingRows);
        $this->discountRepository->replaceByPropertyId($postId, $discountRows);
    }

    private function sanitizePricingRows($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $sanitizedRows = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $startDate = sanitize_text_field((string) ($row['start_date'] ?? ''));
            $endDate = sanitize_text_field((string) ($row['end_date'] ?? ''));
            $price = isset($row['price']) ? (float) $row['price'] : 0.0;

            if (! $this->isValidDate($startDate) || ! $this->isValidDate($endDate) || $price <= 0) {
                continue;
            }

            if ($startDate > $endDate) {
                continue;
            }

            $sanitizedRows[] = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'price' => $price,
            ];
        }

        return $sanitizedRows;
    }

    private function sanitizeDiscountRows($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $sanitizedRows = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $minNights = isset($row['min_nights']) ? (int) $row['min_nights'] : 0;
            $discountType = sanitize_text_field((string) ($row['discount_type'] ?? 'percentage'));
            $discountValue = isset($row['discount_value']) ? (float) $row['discount_value'] : 0.0;

            if ($minNights < 1 || $discountValue <= 0) {
                continue;
            }

            if (! in_array($discountType, ['percentage', 'fixed'], true)) {
                $discountType = 'percentage';
            }

            $sanitizedRows[] = [
                'min_nights' => $minNights,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
            ];
        }

        return $sanitizedRows;
    }

    private function isValidDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function renderPricingRow(int $index, array $row): void
    {
        ?>
        <tr>
            <td>
                <input type="date" name="pbs_pricing_rows[<?php echo esc_attr((string) $index); ?>][start_date]" value="<?php echo esc_attr((string) ($row['start_date'] ?? '')); ?>" />
            </td>
            <td>
                <input type="date" name="pbs_pricing_rows[<?php echo esc_attr((string) $index); ?>][end_date]" value="<?php echo esc_attr((string) ($row['end_date'] ?? '')); ?>" />
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="pbs_pricing_rows[<?php echo esc_attr((string) $index); ?>][price]" value="<?php echo esc_attr((string) ($row['price'] ?? '')); ?>" />
            </td>
            <td>
                <button type="button" class="button-link-delete pbs-remove-btn"><?php esc_html_e('Remove', 'property-booking-system'); ?></button>
            </td>
        </tr>
        <?php
    }

    private function renderDiscountRow(int $index, array $row): void
    {
        $type = (string) ($row['discount_type'] ?? 'percentage');
        ?>
        <tr>
            <td>
                <input type="number" min="1" name="pbs_discount_rows[<?php echo esc_attr((string) $index); ?>][min_nights]" value="<?php echo esc_attr((string) ($row['min_nights'] ?? '')); ?>" />
            </td>
            <td>
                <select name="pbs_discount_rows[<?php echo esc_attr((string) $index); ?>][discount_type]">
                    <option value="percentage" <?php selected($type, 'percentage'); ?>><?php esc_html_e('Percentage (%)', 'property-booking-system'); ?></option>
                    <option value="fixed" <?php selected($type, 'fixed'); ?>><?php esc_html_e('Fixed Amount', 'property-booking-system'); ?></option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="pbs_discount_rows[<?php echo esc_attr((string) $index); ?>][discount_value]" value="<?php echo esc_attr((string) ($row['discount_value'] ?? '')); ?>" />
            </td>
            <td>
                <button type="button" class="button-link-delete pbs-remove-btn"><?php esc_html_e('Remove', 'property-booking-system'); ?></button>
            </td>
        </tr>
        <?php
    }
}
