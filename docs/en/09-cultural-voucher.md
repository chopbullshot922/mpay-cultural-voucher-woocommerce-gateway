# Cultural Voucher

## What Is the Cultural Voucher

The Cultural Voucher is a Moldova government program that provides citizens with vouchers for purchasing cultural goods and services. The vouchers are issued to individuals and can be redeemed at participating stores through the MPay payment system.

This plugin supports Cultural Voucher payments as part of the MPay integration.

## Enabling Cultural Voucher Support

1. Go to WooCommerce > Settings > Payments > MPay / Cultural Voucher > Cultural Voucher tab
2. Toggle "Enable Cultural Voucher" to on
3. Configure eligible product categories
4. Enable the IDNP checkout field
5. Save changes

## Product Eligibility

Not all products in your store may qualify for Cultural Voucher payments. You must configure which WooCommerce product categories are eligible:

- Only products in eligible categories can be paid with a Cultural Voucher
- If a cart contains a mix of eligible and ineligible products, the Cultural Voucher option may apply only to the eligible portion
- Eligibility is checked at checkout time before the payment method is offered

### Configuring Eligible Categories

In the Cultural Voucher tab, select one or more product categories from the dropdown. Only products assigned to these categories will be considered voucher-eligible.

If no categories are selected, the Cultural Voucher payment option will not appear at checkout.

## IDNP Payer Identification

IDNP (Numarul de Identificare de Stat al Persoanei) is the state identification number for individuals in Moldova. Cultural Voucher payments require the payer's IDNP to validate voucher ownership.

When Cultural Voucher is enabled:

- An IDNP field appears at checkout when the customer selects the Cultural Voucher payment method
- The field validates that the input is a 13-digit number
- The IDNP is sent to MPay as part of the payment request
- The IDNP is stored as order meta for record-keeping

## Partial Payments

Cultural Vouchers may not cover the full order amount. When a voucher's remaining balance is less than the order total:

- MPay handles the split payment logic on their side
- The store receives confirmation for the portion paid by voucher
- The remaining amount may be paid by other means (as managed by MPay)
- The ConfirmOrderPayment callback includes the actual amount paid via voucher

The plugin records the partial payment amount in order meta.

## Checkout Flow with Cultural Voucher

1. Customer adds eligible products to cart
2. At checkout, "Cultural Voucher" appears as a payment option (alongside or instead of regular MPay, depending on configuration)
3. Customer enters their IDNP in the identification field
4. Customer clicks "Place Order"
5. Browser redirects to MPay where voucher authorization happens
6. MPay calls back to confirm payment
7. Order is marked as paid

## Order Meta Stored

For Cultural Voucher payments, the following additional meta is stored on the order:

- Payer IDNP (masked in admin display for privacy)
- Voucher reference number
- Voucher amount applied
- Whether payment was full or partial

## Validation Rules

The plugin enforces these rules at checkout:

- IDNP must be exactly 13 digits
- IDNP field is required when Cultural Voucher payment method is selected
- At least one item in the cart must belong to an eligible category
- If no eligible items exist, the Cultural Voucher payment method is hidden

## Admin Display

In the order details admin page, Cultural Voucher payments are identified with:

- Payment method label showing "Cultural Voucher"
- IDNP displayed in the payment meta box (partially masked)
- Voucher reference in order notes
- Amount paid via voucher vs total order amount

## Reporting

Cultural Voucher payments can be filtered in WooCommerce order lists by payment method. Use the standard WooCommerce reporting tools to track voucher payment volumes.
