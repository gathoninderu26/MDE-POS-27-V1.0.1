<?php

namespace App\Services;

use App\Models\Purchase;

class InvoiceParserService
{
    /**
     * Parse raw QR code string or URL into structured fiscal/invoice metadata.
     */
    public function parseQrPayload(?string $payload): array
    {
        if (empty($payload)) {
            return [
                'is_detected' => false,
                'invoice_number' => null,
                'tax_pin' => null,
                'control_unit_code' => null,
                'invoice_date' => null,
                'total_amount' => null,
                'raw' => null,
            ];
        }

        $payload = trim($payload);
        $result = [
            'is_detected' => true,
            'invoice_number' => null,
            'tax_pin' => null,
            'control_unit_code' => null,
            'invoice_date' => null,
            'total_amount' => null,
            'raw' => $payload,
        ];

        // Case 1: URL with Query String (e.g. KRA eTIMS verification URLs)
        if (filter_var($payload, FILTER_VALIDATE_URL) || str_contains($payload, '?')) {
            $parsedUrl = parse_url($payload);
            if (! empty($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);

                // Check common query parameters across fiscal systems
                $result['invoice_number'] = $queryParams['inv']
                    ?? $queryParams['invoice_no']
                    ?? $queryParams['invoice']
                    ?? $queryParams['cu_inv']
                    ?? $queryParams['r']
                    ?? null;

                $result['tax_pin'] = $queryParams['pin']
                    ?? $queryParams['tax_pin']
                    ?? $queryParams['tin']
                    ?? $queryParams['seller_pin']
                    ?? null;

                $result['control_unit_code'] = $queryParams['cu']
                    ?? $queryParams['scu']
                    ?? $queryParams['cu_serial']
                    ?? null;

                $result['total_amount'] = isset($queryParams['tot'])
                    ? (float) str_replace(',', '', $queryParams['tot'])
                    : (isset($queryParams['total']) ? (float) str_replace(',', '', $queryParams['total']) : null);

                $result['invoice_date'] = $queryParams['date']
                    ?? $queryParams['dt']
                    ?? (isset($queryParams['time']) ? substr($queryParams['time'], 0, 10) : null);
            }
        }

        // Case 2: JSON formatted QR payload
        if (str_starts_with($payload, '{') && str_ends_with($payload, '}')) {
            $json = json_decode($payload, true);
            if (is_array($json)) {
                $result['invoice_number'] = $json['invoice_number'] ?? $json['invoice_no'] ?? $json['inv'] ?? $result['invoice_number'];
                $result['tax_pin'] = $json['tax_pin'] ?? $json['pin'] ?? $json['tin'] ?? $result['tax_pin'];
                $result['control_unit_code'] = $json['control_unit_code'] ?? $json['cu_serial'] ?? $result['control_unit_code'];
                $result['total_amount'] = isset($json['total_amount']) ? (float) $json['total_amount'] : (isset($json['total']) ? (float) $json['total'] : $result['total_amount']);
                $result['invoice_date'] = $json['invoice_date'] ?? $json['date'] ?? $result['invoice_date'];
            }
        }

        // Case 3: Delimited Key-Value format (e.g. PIN:P051234567;INV:INV-992;TOT:14500)
        if (str_contains($payload, ':') || str_contains($payload, '=')) {
            $tokens = preg_split('/[;|\n,]/', $payload);
            foreach ($tokens as $token) {
                if (str_contains($token, ':')) {
                    [$k, $v] = explode(':', trim($token), 2);
                } elseif (str_contains($token, '=')) {
                    [$k, $v] = explode('=', trim($token), 2);
                } else {
                    continue;
                }

                $key = strtolower(trim($k));
                $val = trim($v);

                if (in_array($key, ['pin', 'tax_pin', 'tin', 'seller_pin']) && empty($result['tax_pin'])) {
                    $result['tax_pin'] = $val;
                } elseif (in_array($key, ['inv', 'invoice', 'invoice_no', 'receipt']) && empty($result['invoice_number'])) {
                    $result['invoice_number'] = $val;
                } elseif (in_array($key, ['tot', 'total', 'amt', 'amount']) && empty($result['total_amount'])) {
                    $result['total_amount'] = (float) str_replace(',', '', $val);
                } elseif (in_array($key, ['date', 'dt']) && empty($result['invoice_date'])) {
                    $result['invoice_date'] = $val;
                } elseif (in_array($key, ['cu', 'scu', 'device']) && empty($result['control_unit_code'])) {
                    $result['control_unit_code'] = $val;
                }
            }
        }

        // Fallback: Check if payload itself is just an invoice string (e.g. "INV-2026-0042")
        if (empty($result['invoice_number']) && ! filter_var($payload, FILTER_VALIDATE_URL)) {
            if (preg_match('/(INV|REC|BILL|PO|LPO|TAX)[-_0-9A-Z]{3,}/i', $payload, $m)) {
                $result['invoice_number'] = $m[0];
            }
        }

        return $result;
    }

    /**
     * Compare Purchase Order line items against physical invoiced items.
     * Generates a detailed side-by-side reconciliation matrix with variance detection.
     */
    public function compare(Purchase $purchase, array $invoicedItems, float $invoicedTotal): array
    {
        // 1. Extract PO structured items
        $poItems = [];
        if (! empty($purchase->notes)) {
            $decoded = json_decode($purchase->notes, true);
            if (is_array($decoded)) {
                $poItems = $decoded;
            }
        }

        // If no JSON bulk items, construct single virtual line from description
        if (empty($poItems)) {
            $poItems = [[
                'product_id' => null,
                'name' => $purchase->items_description ?: 'General Supplies',
                'sku' => '',
                'package_unit' => 'Units',
                'packs' => 1,
                'quantity' => 1,
                'unit_cost' => (float) $purchase->total_amount,
                'total' => (float) $purchase->total_amount,
            ]];
        }

        $comparisonRows = [];
        $matchedInvoiceKeys = [];
        $hasPriceVariance = false;
        $hasQtyVariance = false;
        $hasMissingItems = false;

        // 2. Loop through each PO expected item and attempt to find a match in the scanned invoice
        foreach ($poItems as $poItem) {
            $poName = strtolower(trim($poItem['name'] ?? ''));
            $poSku = strtolower(trim($poItem['sku'] ?? ''));
            $poQty = (float) ($poItem['quantity'] ?? $poItem['total_units'] ?? 1);
            $poCost = (float) ($poItem['unit_cost'] ?? 0);
            $poLineTotal = (float) ($poItem['total'] ?? $poItem['line_total'] ?? ($poQty * $poCost));

            // Try to match by SKU first, then by name match
            $matchedIndex = null;
            foreach ($invoicedItems as $idx => $invItem) {
                if (in_array($idx, $matchedInvoiceKeys, true)) {
                    continue;
                }

                $invSku = strtolower(trim($invItem['sku'] ?? ''));
                $invName = strtolower(trim($invItem['name'] ?? $invItem['description'] ?? ''));

                if (! empty($poSku) && ! empty($invSku) && $poSku === $invSku) {
                    $matchedIndex = $idx;
                    break;
                }

                if ($poName !== '' && $invName !== '' && (str_contains($invName, $poName) || str_contains($poName, $invName))) {
                    $matchedIndex = $idx;
                    break;
                }
            }

            if ($matchedIndex !== null) {
                $matchedInvoiceKeys[] = $matchedIndex;
                $invItem = $invoicedItems[$matchedIndex];

                $invQty = (float) ($invItem['quantity'] ?? $invItem['qty'] ?? 0);
                $invCost = (float) ($invItem['unit_cost'] ?? $invItem['unit_price'] ?? 0);
                $invTotal = (float) ($invItem['total'] ?? $invItem['line_total'] ?? ($invQty * $invCost));

                $qtyDiff = $invQty - $poQty;
                $costDiff = $invCost - $poCost;
                $totalDiff = $invTotal - $poLineTotal;

                $rowStatus = 'EXACT_MATCH';
                if (abs($costDiff) > 0.01 && abs($qtyDiff) > 0.001) {
                    $rowStatus = 'BOTH_VARIANCE';
                    $hasPriceVariance = true;
                    $hasQtyVariance = true;
                } elseif (abs($costDiff) > 0.01) {
                    $rowStatus = 'PRICE_VARIANCE';
                    $hasPriceVariance = true;
                } elseif (abs($qtyDiff) > 0.001) {
                    $rowStatus = 'QTY_VARIANCE';
                    $hasQtyVariance = true;
                }

                $comparisonRows[] = [
                    'item_name' => $poItem['name'] ?? 'Item',
                    'sku' => $poItem['sku'] ?? '',
                    'product_id' => $poItem['product_id'] ?? null,
                    'package_unit' => $poItem['package_unit'] ?? 'Units',
                    'po_quantity' => $poQty,
                    'invoiced_quantity' => $invQty,
                    'quantity_variance' => $qtyDiff,
                    'po_unit_cost' => $poCost,
                    'invoiced_unit_cost' => $invCost,
                    'price_variance' => $costDiff,
                    'po_total' => $poLineTotal,
                    'invoiced_total' => $invTotal,
                    'variance_amount' => $totalDiff,
                    'status' => $rowStatus,
                ];
            } else {
                // PO item not found in invoice
                $hasMissingItems = true;
                $comparisonRows[] = [
                    'item_name' => $poItem['name'] ?? 'Item',
                    'sku' => $poItem['sku'] ?? '',
                    'product_id' => $poItem['product_id'] ?? null,
                    'package_unit' => $poItem['package_unit'] ?? 'Units',
                    'po_quantity' => $poQty,
                    'invoiced_quantity' => 0,
                    'quantity_variance' => -$poQty,
                    'po_unit_cost' => $poCost,
                    'invoiced_unit_cost' => 0,
                    'price_variance' => -$poCost,
                    'po_total' => $poLineTotal,
                    'invoiced_total' => 0,
                    'variance_amount' => -$poLineTotal,
                    'status' => 'MISSING_IN_INVOICE',
                ];
            }
        }

        // 3. Any extra items on the invoice that were NOT ordered in the PO
        $hasUnexpectedItems = false;
        foreach ($invoicedItems as $idx => $invItem) {
            if (! in_array($idx, $matchedInvoiceKeys, true)) {
                $hasUnexpectedItems = true;
                $invQty = (float) ($invItem['quantity'] ?? $invItem['qty'] ?? 1);
                $invCost = (float) ($invItem['unit_cost'] ?? $invItem['unit_price'] ?? 0);
                $invTotal = (float) ($invItem['total'] ?? $invItem['line_total'] ?? ($invQty * $invCost));

                $comparisonRows[] = [
                    'item_name' => $invItem['name'] ?? $invItem['description'] ?? 'Extra Unordered Item',
                    'sku' => $invItem['sku'] ?? '',
                    'product_id' => null,
                    'package_unit' => 'Units',
                    'po_quantity' => 0,
                    'invoiced_quantity' => $invQty,
                    'quantity_variance' => $invQty,
                    'po_unit_cost' => 0,
                    'invoiced_unit_cost' => $invCost,
                    'price_variance' => $invCost,
                    'po_total' => 0,
                    'invoiced_total' => $invTotal,
                    'variance_amount' => $invTotal,
                    'status' => 'UNEXPECTED_ITEM',
                ];
            }
        }

        $poTotal = (float) $purchase->total_amount;
        $varianceTotal = round($invoicedTotal - $poTotal, 2);

        // Determine Overall Reconciliation Status
        $reconciliationStatus = 'MATCHED';
        if ($hasUnexpectedItems || $hasMissingItems) {
            $reconciliationStatus = 'DISCREPANCY';
        } elseif ($hasPriceVariance && $hasQtyVariance) {
            $reconciliationStatus = 'PRICE_VARIANCE';
        } elseif ($hasPriceVariance) {
            $reconciliationStatus = 'PRICE_VARIANCE';
        } elseif ($hasQtyVariance) {
            $reconciliationStatus = 'QTY_VARIANCE';
        } elseif (abs($varianceTotal) > 0.05) {
            $reconciliationStatus = 'PRICE_VARIANCE';
        }

        return [
            'rows' => $comparisonRows,
            'po_total' => $poTotal,
            'invoiced_total' => $invoicedTotal,
            'variance_total' => $varianceTotal,
            'reconciliation_status' => $reconciliationStatus,
            'has_variance' => $reconciliationStatus !== 'MATCHED',
        ];
    }
}
