<?php
// Shared money helpers for Checkout.
//
// All arithmetic is done in whole cents (integers), never floating-point
// dollars, so there are no rounding surprises like 0.1 + 0.2 = 0.30000000000000004.

// Credit card fee, in basis points (1 basis point = 0.01%).
//   300 = 3.00%     250 = 2.5%     0 = no fee
// Change this ONE number to change the card fee everywhere.
const CARD_FEE_BASIS_POINTS = 300;

// Card fee for a subtotal, in cents, rounded half up (integer math only).
function cardFeeCents(int $subtotalCents): int
{
    return intdiv($subtotalCents * CARD_FEE_BASIS_POINTS + 5000, 10000);
}

// 7050 -> "70.50"
function centsToString(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

// "70.5" or "70.50" -> 7050. Returns null if it isn't a valid dollar amount.
function stringToCents(string $amount): ?int
{
    if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', $amount, $m)) {
        return null;
    }
    $fraction = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';
    return ((int) $m[1]) * 100 + (int) $fraction;
}
