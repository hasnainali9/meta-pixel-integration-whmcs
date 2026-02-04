<?php
if (!defined("WHMCS")) die();

use WHMCS\Database\Capsule;

function meta_pixel_config()
{
    return [
        'name' => 'Meta Pixel Integration (WHMCS v9)',
        'description' => 'Meta Pixel (Facebook Pixel) integration for the WHMCS client area with standard event tracking.',
        'version' => '1.1.0',
        'author' => 'Hasnain Ali',
        'fields' => [
            'pixel_id' => [
                'FriendlyName' => 'Meta Pixel ID',
                'Type' => 'text',
                'Size' => '25',
                'Default' => '3244586562373140',
                'Description' => 'Enter your Meta Pixel ID (numbers only).',
            ],
            'hook_locations_head' => [
                'FriendlyName' => 'Client Area Head',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Inject Meta Pixel in the &lt;head&gt; section of all client area pages (recommended)',
            ],
            'hook_locations_header' => [
                'FriendlyName' => 'Client Area Header',
                'Type' => 'yesno',
                'Default' => '',
                'Description' => 'Inject Meta Pixel at the top of the page body',
            ],
            'hook_locations_footer' => [
                'FriendlyName' => 'Client Area Footer',
                'Type' => 'yesno',
                'Default' => '',
                'Description' => 'Inject Meta Pixel at the bottom of the page',
            ],
            'hook_locations_product' => [
                'FriendlyName' => 'Product Details Page (ViewContent)',
                'Type' => 'yesno',
                'Default' => '',
                'Description' => 'Track ViewContent on product pages',
            ],
            'hook_locations_checkout' => [
                'FriendlyName' => 'Shopping Cart Checkout (InitiateCheckout)',
                'Type' => 'yesno',
                'Default' => '',
                'Description' => 'Track InitiateCheckout on the checkout page',
            ],
            'track_purchase' => [
                'FriendlyName' => 'Order Confirmation (Purchase)',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Track Purchase event on the order complete/receipt page (recommended)',
            ],
            'purchase_value_source' => [
                'FriendlyName' => 'Purchase Value Source',
                'Type' => 'dropdown',
                'Options' => 'auto,carttotal,ordertotal,invoice',
                'Default' => 'auto',
                'Description' => 'How to determine the Purchase value (auto tries available vars).',
            ],
            'purchase_currency' => [
                'FriendlyName' => 'Purchase Currency (optional)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '',
                'Description' => 'Leave blank to use WHMCS currency when available (e.g. USD).',
            ],
        ],
    ];
}

/**
 * Admin area output
 */
function meta_pixel_output($vars)
{
    // Get the addon settings
    $pixelIdRaw = isset($vars['pixel_id']) ? (string) $vars['pixel_id'] : '3244586562373140';
    $pixelIdSafe = preg_replace('/\D+/', '', $pixelIdRaw);

    // Map yes/no settings to hook locations
    $hookLocations = [];
    if (isset($vars['hook_locations_head']) && $vars['hook_locations_head'] == 'on') {
        $hookLocations[] = 'ClientAreaHeadOutput';
    }
    if (isset($vars['hook_locations_header']) && $vars['hook_locations_header'] == 'on') {
        $hookLocations[] = 'ClientAreaHeaderOutput';
    }
    if (isset($vars['hook_locations_footer']) && $vars['hook_locations_footer'] == 'on') {
        $hookLocations[] = 'ClientAreaFooterOutput';
    }
    if (isset($vars['hook_locations_product']) && $vars['hook_locations_product'] == 'on') {
        $hookLocations[] = 'ClientAreaProductDetailsOutput';
    }
    if (isset($vars['hook_locations_checkout']) && $vars['hook_locations_checkout'] == 'on') {
        $hookLocations[] = 'ShoppingCartCheckoutOutput';
    }

    // If no hooks are enabled, default to head
    if (empty($hookLocations)) {
        $hookLocations[] = 'ClientAreaHeadOutput';
    }

    $eventsEnabled = [
        'ViewContent' => (isset($vars['hook_locations_product']) && $vars['hook_locations_product'] == 'on'),
        'InitiateCheckout' => (isset($vars['hook_locations_checkout']) && $vars['hook_locations_checkout'] == 'on'),
        'Purchase' => (isset($vars['track_purchase']) && $vars['track_purchase'] == 'on'),
    ];

    // Available hook locations
    $availableLocations = [
        'ClientAreaHeadOutput' => 'Client Area &lt;head&gt;',
        'ClientAreaHeaderOutput' => 'Client Area Header',
        'ClientAreaFooterOutput' => 'Client Area Footer',
        'ClientAreaProductDetailsOutput' => 'Product Details Page',
        'ShoppingCartCheckoutOutput' => 'Shopping Cart Checkout'
    ];

    // Styles (kept minimal and scoped)
    echo <<<HTML
<style>
#meta-pixel-wrap{max-width:1100px}
#meta-pixel-wrap .mp-grid{display:grid;grid-template-columns:1fr;gap:16px}
@media (min-width: 992px){#meta-pixel-wrap .mp-grid{grid-template-columns:1fr 1fr}}
#meta-pixel-wrap .mp-card{background:#fff;border:1px solid #e5e5e5;border-radius:6px;padding:16px}
#meta-pixel-wrap .mp-title{margin:0 0 8px 0;font-weight:600}
#meta-pixel-wrap .mp-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
#meta-pixel-wrap code{background:#f5f5f5;padding:2px 6px;border-radius:4px}
#meta-pixel-wrap .mp-table td,#meta-pixel-wrap .mp-table th{vertical-align:middle}
#meta-pixel-wrap .mp-muted{color:#6b7280}
</style>
HTML;

    echo '<div id="meta-pixel-wrap">';

    // Header summary
    echo '<div class="mp-card" style="margin-bottom:16px">';
    echo '<h2 class="mp-title">Meta Pixel Integration (WHMCS v9)</h2>';
    echo '<p class="mp-muted" style="margin:0">Author: <strong>Hasnain Ali</strong> &middot; Tracks <strong>PageView</strong> and optional standard events.</p>';

    if ($pixelIdSafe !== '') {
        echo '<div class="alert alert-success" style="margin-top:12px">Pixel ID configured: <strong>' . htmlspecialchars($pixelIdSafe) . '</strong></div>';
    } else {
        echo '<div class="alert alert-warning" style="margin-top:12px">Pixel ID looks empty/invalid. Please set a numeric Pixel ID in the Configure tab.</div>';
    }

    echo '<div class="mp-badges">';
    foreach ($eventsEnabled as $event => $enabled) {
        $label = $enabled ? 'label label-success' : 'label label-default';
        $text = $enabled ? 'ENABLED' : 'DISABLED';
        echo '<span class="' . $label . '">' . htmlspecialchars($event) . ': ' . $text . '</span>';
    }
    echo '</div>';
    echo '</div>';

    echo '<div class="mp-grid">';

    // Hook locations card
    echo '<div class="mp-card">';
    echo '<h3 class="mp-title">Hook Locations</h3>';
    echo '<p class="mp-muted">These settings control where the base pixel code is injected. Even if multiple locations are enabled, the pixel is injected <strong>only once</strong> to prevent duplicates.</p>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered mp-table">';
    echo '<thead><tr><th>Location</th><th>Status</th><th>Hook Name</th></tr></thead>';
    echo '<tbody>';
    foreach ($availableLocations as $hook => $friendlyName) {
        $isOn = in_array($hook, $hookLocations);
        $badge = $isOn ? '<span class="label label-success">ENABLED</span>' : '<span class="label label-default">DISABLED</span>';
        echo '<tr><td>' . $friendlyName . '</td><td>' . $badge . '</td><td><code>' . htmlspecialchars($hook) . '</code></td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<div class="alert alert-info" style="margin-top:12px">To change these, open the <strong>Configure</strong> tab above and check/uncheck the desired locations.</div>';
    echo '</div>';

    // Events card
    echo '<div class="mp-card">';
    echo '<h3 class="mp-title">Events</h3>';
    echo '<ul style="margin:0 0 10px 18px">';
    echo '<li><strong>PageView</strong> is tracked on every client area page where the base pixel loads.</li>';
    echo '<li><strong>ViewContent</strong> is tracked on product details pages (optional).</li>';
    echo '<li><strong>InitiateCheckout</strong> is tracked on the checkout page (optional).</li>';
    echo '<li><strong>Purchase</strong> is tracked on order completion / invoice contexts (optional).</li>';
    echo '</ul>';

    $purchaseSource = htmlspecialchars((string)($vars['purchase_value_source'] ?? 'auto'));
    $purchaseCurrency = htmlspecialchars((string)($vars['purchase_currency'] ?? ''));

    echo '<p class="mp-muted" style="margin:0">Purchase value source: <code>' . $purchaseSource . '</code>';
    if ($purchaseCurrency !== '') {
        echo ' &middot; currency override: <code>' . $purchaseCurrency . '</code>';
    }
    echo '</p>';

    echo '<hr style="margin:14px 0">';
    echo '<h4 class="mp-title" style="font-size:14px">Base Pixel Code Preview</h4>';

    $pixelForExample = $pixelIdSafe !== '' ? $pixelIdSafe : 'YOUR_PIXEL_ID';

    $exampleCode = <<<HTML
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
 n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
 n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
 t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
 'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelForExample}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$pixelForExample}&ev=PageView&noscript=1" /></noscript>
<!-- End Meta Pixel Code -->
HTML;

    echo '<pre style="margin-top:10px; background:#0b1020; color:#e5e7eb; padding:12px; border-radius:6px; overflow:auto">' . htmlspecialchars($exampleCode) . '</pre>';
    echo '</div>';

    echo '</div>'; // grid
    echo '</div>'; // wrap
}

/**
 * Activate the module
 */
function meta_pixel_activate()
{
    // No activation tasks needed
    return [
        'status' => 'success',
        'description' => 'Meta Pixel Integration module activated successfully.',
    ];
}

/**
 * Deactivate the module
 */
function meta_pixel_deactivate()
{
    // No deactivation tasks needed
    return [
        'status' => 'success',
        'description' => 'Meta Pixel Integration module deactivated successfully.',
    ];
}
