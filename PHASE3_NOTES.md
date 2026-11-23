# Digital Canteen - Phase 3 notes

Cart, checkout, orders, tokens, cash payment, demo online payment, order history.

## Important: the online payment is a DEMO

"Demo Online Payment" is a classroom simulation. **There is no real payment gateway.**
No bank, card network or UPI service is contacted, and no card number, UPI PIN, CVV or banking
detail is requested or stored.

The browser only tells the server which demo scenario to show ("success" or "failure") so both
outcomes can be demonstrated. It never sends a payment status. The server function
`demo_payment_simulate()` in `includes/orders.php` alone decides whether a payment is PAID,
and an order is saved only if it says yes. A failed demo payment saves nothing.

## Install

1. Copy the files over your existing `digital_canteen` folder.
2. phpMyAdmin > select `digital_canteen` > Import > `database/phase3_orders.sql`.
   (Do NOT re-import `canteen.sql`. The migration is safe to run more than once.)

## What the migration changes

Phase 1 already created `orders`, `order_items`, `payments` and `tokens`, so Phase 3 reuses them:

* `payments.method` gains the value `DEMO_ONLINE` (existing `CASH` and `DEMO` stay valid).
* `orders.checkout_token` (new, UNIQUE) makes a repeated or refreshed checkout return the same order.

Nothing is dropped, deleted or reset.

## Where things are

| Purpose | File |
|---|---|
| Cart logic | `includes/cart.php`, `api/customer/cart.php`, `customer/cart.php`, `assets/js/cart.js` |
| Order, token, demo simulator | `includes/orders.php` |
| Checkout | `api/customer/checkout.php`, `customer/checkout.php`, `assets/js/checkout.js` |
| Confirmation / details | `customer/order-success.php`, `customer/order.php`, `includes/order_view.php` |
| History | `api/orders/history.php`, `customer/orders.php`, `assets/js/orders.js` |
