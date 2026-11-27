# Checkout & Orders

> [!IMPORTANT]
> **BA 3103 · Group 4** owns this module.

## Team — Group 4
| | |
| --- | --- |
| Group GitHub account | @BreniAlco |
| Members | 5 (names are in the class tracker) |

## Scope
Checkout, order creation from the cart, customer order history and detail, cancellation, and admin order management. **This is the hub module: six teams depend on it.** Keep `OrderServiceInterface` and the order events stable.

## Progress
**Difficulty:** Hard · starter about 40% done

> [!NOTE]
> **Already done in the starter: do not rebuild these.** Read this code first; your work builds on it.
>
> - [x] `Order` and `OrderItem` models, status constants and the allowed `TRANSITIONS` map with `canTransitionTo()`
> - [x] Checkout page and `createFromCart()` (totals, items, clears cart, fires `OrderPlaced`)
> - [x] Customer order history (`/orders`)
> - [x] `hasPurchased()` (used by Reviews team)
> - [x] Listeners `MarkOrderPaid` and `MarkOrderShipped`

**Your work: Must items (graded)**
- [x] 1. Order detail page (`/orders/{order}`): items, totals, address, payment status, tracking from `ShippingServiceInterface`; owner only
- [x] 2. Cancel a pending order (`POST /orders/{order}/cancel`); `updateStatus()` enforces `canTransitionTo()` and fires `OrderCancelled`
- [x] 3. Admin order list with a status dropdown that calls `updateStatus()` (`/admin/orders`)
- [x] 4. Re-check stock for every item before placing the order + feature tests (totals, empty cart, cancel, invalid transition)

**Your work: Stretch items (bonus, after all Musts)**
- [x] Address picker at checkout (after Users team's address book)
- [x] Admin order filters by status and date, admin order detail page

## Interface provided — `App\Contracts\OrderServiceInterface`
| Method | Parameters | Returns | Status |
| --- | --- | --- | --- |
| `createFromCart` | `int $userId, array $checkout` (address_id, voucher_code, payment_method) | `Order` | Done (includes stock re-check) |
| `find` | `int $orderId` | `?Order` with items | Done |
| `updateStatus` | `int $orderId, string $status` | `Order` | Done (enforces transitions, fires `OrderCancelled`) |
| `hasPurchased` | `int $userId, int $productId` | `bool` | Done |

## Events
- Fires: `OrderPlaced` (done), `OrderCancelled` (done)
- Listens to: `PaymentConfirmed` → `MarkOrderPaid` (done), `OrderShipped` → `MarkOrderShipped` (done)

## Dependencies used
| Module | Interface / method | Why |
| --- | --- | --- |
| Users team | `UserServiceInterface::getDefaultAddress` | Shipping address |
| Catalog team | `CatalogServiceInterface::isAvailable` | Stock re-check before order creation |
| Cart team | `CartServiceInterface::getItems`, `getSubtotal`, `clear` | Build the order |
| Vouchers (unassigned) | `VoucherServiceInterface::apply` | Discount |
| Payments team | `PaymentServiceInterface::createPayment` | Payment record / online checkout |
| Shipping team | `ShippingServiceInterface::getShippingFee`, `getTracking` | Shipping fee and order tracking |

## Database
| Table | Columns | Migration file |
| --- | --- | --- |
| `orders` (core) | user_id, status, subtotal, discount, shipping_fee, total, voucher_code, payment_method, address_id, shipping_address | `database/migrations/2026_01_01_000200_create_orders_tables.php` |
| `order_items` (core) | order_id, product_id, product_name, quantity, unit_price | same file |

## Routes
| Method | URI | Controller@action | Middleware |
| --- | --- | --- | --- |
| GET | `/checkout` | `CheckoutController@show` | auth |
| POST | `/checkout` | `CheckoutController@store` | auth |
| GET | `/orders` | `OrderController@index` | auth |
| GET | `/orders/{order}` | `OrderController@show` | auth |
| POST | `/orders/{order}/cancel` | `OrderController@cancel` | auth |
| GET | `/admin/orders` | `AdminOrderController@index` | auth, admin |
| GET | `/admin/orders/{order}` | `AdminOrderController@show` | auth, admin |
| PATCH | `/admin/orders/{order}/status` | `AdminOrderController@updateStatus` | auth, admin |

## Setup and testing
- Manual test: add items to the cart, check out with COD, see the order in `/orders` and `/orders/{order}`, cancel a pending order, and manage statuses in `/admin/orders`
- Automated tests: `php artisan test tests/Feature/Orders/OrdersModuleTest.php`

## Changelog
| Date | Change | PR # | Author |
| --- | --- | --- | --- |
| | Starter code from instructor | — | Instructor |
| 2026-11-27 | Implement Must 1–4: customer order detail (`/orders/{order}`) with items, totals, address, payment status, and `ShippingServiceInterface` tracking; customer pending order cancellation (`POST /orders/{order}/cancel`) + `OrderService::updateStatus()` transition enforcement and `OrderCancelled` event; admin order list/detail and status updates (`/admin/orders`); checkout stock re-check (`CatalogServiceInterface::isAvailable()`) and `OrdersModuleTest` feature tests | — | Group 4 |
| 2026-11-27 | Review & polish Checkout & Orders: add checkout address picker with ownership validation, voucher code normalization/preview/validation, transactional status locking, status badges & order progress timeline, customer order status filter & empty states, admin order search/status/date filters, responsive UI refresh, and expanded `OrdersModuleTest` coverage | — | Jasmine Ramos (@jaesumin02) |
