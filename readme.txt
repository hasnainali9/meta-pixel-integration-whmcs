=== Meta Pixel Integration (WHMCS v9) ===
Contributors: Hasnain Ali
Tags: whmcs, meta pixel, facebook pixel, tracking, events
Requires: WHMCS 9.x
Version: 1.1.0
License: Proprietary

== Overview ==
Meta Pixel Integration for WHMCS v9.

This addon injects the Meta (Facebook) Pixel in the WHMCS client area and tracks standard events.

Tracked events:
- PageView (always when the pixel loads)
- ViewContent (optional, product details pages)
- InitiateCheckout (optional, checkout page)
- Purchase (optional, order complete / invoice contexts)

The integration is designed to be safe and robust:
- The pixel is injected only once even if multiple output locations are enabled.
- Dynamic values are escaped safely for JavaScript.
- If the Pixel ID is missing/invalid, it fails safely (no output).

== Installation (WHMCS v9) ==
1) Upload the module folder to your WHMCS installation:
   /modules/addons/meta_pixel/

2) Ensure these files exist in that folder:
   - meta_pixel.php
   - hooks.php

3) In WHMCS Admin:
   Setup -> Addon Modules
   - Find "Meta Pixel Integration (WHMCS v9)"
   - Click Activate
   - Configure access control (which admin roles can access)

== Configuration ==
Go to:
Setup -> Addon Modules -> Meta Pixel Integration (WHMCS v9) -> Configure

Settings:
- Meta Pixel ID
  Enter your numeric Pixel ID.

- Hook Locations
  Choose where the base pixel code is injected:
  - Client Area Head (recommended)
  - Client Area Header
  - Client Area Footer

  Tip: Even if you enable multiple locations, the module will inject the pixel only once to prevent duplicates.

- Events
  - Product Details Page (ViewContent)
    Tracks ViewContent on WHMCS product details pages.

  - Shopping Cart Checkout (InitiateCheckout)
    Tracks InitiateCheckout on the checkout page.

  - Order Confirmation (Purchase)
    Tracks Purchase on order completion / invoice contexts.

  - Purchase Value Source
    Select how the value is derived for the Purchase event:
    - auto: tries invoice total -> order total -> cart total
    - carttotal: uses cart total if available
    - ordertotal: uses order total if available
    - invoice: uses invoice total if available

  - Purchase Currency (optional)
    Leave blank to attempt auto-detection from WHMCS.

== How it works ==
- Base pixel is output via WHMCS client area output hooks.
- Event trackers output only on the relevant page hooks.
- Purchase is added using a client-area page hook and emitted in the footer for reliability.

== Troubleshooting ==
1) Pixel not firing
- Confirm the Pixel ID is correct and numeric.
- Ensure at least one hook location is enabled (Client Area Head is recommended).
- Check browser console/network that https://connect.facebook.net/en_US/fbevents.js loads.

2) Duplicate PageView
- If you have other theme/custom scripts injecting the Meta Pixel, disable one of them.
- This module prevents duplicates from its own hooks, but cannot stop duplicates from unrelated custom code.

3) Purchase value/currency missing
- Set Purchase Value Source to "invoice" or "ordertotal" depending on your checkout flow.
- Set Purchase Currency if auto detection does not work for your setup.

== Security notes ==
- The module sanitizes the Pixel ID to digits only.
- Event data is escaped via JSON encoding to avoid JS injection issues.

== Changelog ==
= 1.1.0 =
- Updated module metadata for WHMCS v9
- Improved admin output UI styling
- Added robust event tracking: ViewContent, InitiateCheckout, Purchase
- Prevented duplicate pixel injection when multiple locations are enabled

