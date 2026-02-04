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
    $pixelId = isset($vars['pixel_id']) ? $vars['pixel_id'] : '3244586562373140';

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

    // Available hook locations
    $availableLocations = [
        'ClientAreaHeadOutput' => 'Client Area Head',
        'ClientAreaHeaderOutput' => 'Client Area Header',
        'ClientAreaFooterOutput' => 'Client Area Footer',
        'ClientAreaProductDetailsOutput' => 'Product Details Page',
        'ShoppingCartCheckoutOutput' => 'Shopping Cart Checkout'
    ];

    // Start output buffer
    echo '<div class="alert alert-success">Meta Pixel is configured and working! Current Pixel ID: <strong>' . htmlspecialchars($pixelId) . '</strong></div>';

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">Meta Pixel Configuration</h3></div>';
    echo '<div class="panel-body">';

    // Current hook locations
    echo '<h4>Current Hook Locations</h4>';
    echo '<p>The Meta Pixel code is currently being injected at these locations:</p>';
    echo '<ul>';

    foreach ($availableLocations as $hook => $friendlyName) {
        if (in_array($hook, $hookLocations)) {
            echo '<li><span class="label label-success">ENABLED</span> ' . htmlspecialchars($friendlyName) . ' (' . htmlspecialchars($hook) . ')</li>';
        } else {
            echo '<li><span class="label label-default">DISABLED</span> ' . htmlspecialchars($friendlyName) . ' (' . htmlspecialchars($hook) . ')</li>';
        }
    }

    echo '</ul>';

    // Information about each hook location
    echo '<h4>Hook Location Information</h4>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered">';
    echo '<thead><tr><th>Hook</th><th>Description</th><th>Example Use Case</th></tr></thead>';
    echo '<tbody>';
    echo '<tr><td>Client Area Head</td><td>Injected in the &lt;head&gt; section of all client area pages</td><td>Standard Meta Pixel implementation</td></tr>';
    echo '<tr><td>Client Area Header</td><td>Injected at the top of the page body</td><td>If you need the pixel to load earlier in the page</td></tr>';
    echo '<tr><td>Client Area Footer</td><td>Injected at the bottom of pages</td><td>If you want the pixel to load after all other content</td></tr>';
    echo '<tr><td>Product Details Page</td><td>Special implementation for product pages with ViewContent event</td><td>Track when customers view specific products</td></tr>';
    echo '<tr><td>Shopping Cart Checkout</td><td>Special implementation for checkout with InitiateCheckout event</td><td>Track when customers start the checkout process</td></tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    echo '<p class="alert alert-info">To change which hooks are enabled, go to the "Configure" tab above and check/uncheck the desired locations.</p>';

    // Example pixel code
    echo '<h4>Example Meta Pixel Code</h4>';

    $exampleCode = <<<HTML
<!-- Meta Pixel Code -->
<script>
    ! function(f, b, e, v, n, t, s) {
        if (f.fbq) return;
        n = f.fbq = function() {
            n.callMethod ?
                n.callMethod.apply(n, arguments) : n.queue.push(arguments)
        };
        if (!f._fbq) f._fbq = n;
        n.push = n;
        n.loaded = !0;
        n.version = '2.0';
        n.queue = [];
        t = b.createElement(e);
        t.async = !0;
        t.src = v;
        s = b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t, s)
    }(window, document, 'script',
        'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{$pixelId}');
    fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1" /></noscript>
<!-- End Meta Pixel Code -->
HTML;

    echo '<pre style="margin-top: 15px; background-color: #f8f8f8; padding: 15px; border-radius: 4px;">' . htmlspecialchars($exampleCode) . '</pre>';

    echo '</div>'; // panel-body
    echo '</div>'; // panel
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
