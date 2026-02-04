<?php

/**
 * Meta Pixel WHMCS Hooks
 * 
 * This file registers the hooks for the Meta Pixel module.
 */

// Make sure this file is not accessed directly
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Register hooks for the Meta Pixel integration
add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    return '';
});

/**
 * Internal helpers
 */
if (!function_exists('meta_pixel_js_string')) {
    function meta_pixel_js_string($value)
    {
        // Safe for embedding into JS string literals.
        return json_encode((string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('meta_pixel_normalize_pixel_id')) {
    function meta_pixel_normalize_pixel_id($pixelId)
    {
        $pixelId = (string) $pixelId;
        // Pixel IDs are numeric; keep digits only for safety.
        $pixelId = preg_replace('/\D+/', '', $pixelId);
        return $pixelId ?: '';
    }
}

if (!function_exists('meta_pixel_load_settings')) {
    function meta_pixel_load_settings()
    {
        $settings = [];
        try {
            $result = localAPI('GetModuleConfiguration', ['module' => 'meta_pixel']);
            if (is_array($result) && ($result['result'] ?? '') === 'success' && isset($result['configuration']) && is_array($result['configuration'])) {
                $settings = $result['configuration'];
            }
        } catch (Exception $e) {
            // Ignore and fall back to defaults
        }
        return $settings;
    }
}

if (!function_exists('meta_pixel_setting_on')) {
    function meta_pixel_setting_on($settings, $key, $default = false)
    {
        if (!is_array($settings) || !array_key_exists($key, $settings)) {
            return (bool) $default;
        }
        // WHMCS yesno typically returns "on" when enabled.
        return $settings[$key] === 'on' || $settings[$key] === '1' || $settings[$key] === 1 || $settings[$key] === true;
    }
}

if (!function_exists('meta_pixel_get_currency')) {
    function meta_pixel_get_currency($vars, $settings)
    {
        $currency = trim((string) ($settings['purchase_currency'] ?? ''));
        if ($currency !== '') {
            return strtoupper($currency);
        }

        // Attempt to infer from common WHMCS variables.
        if (isset($vars['currency']['code']) && is_string($vars['currency']['code']) && $vars['currency']['code'] !== '') {
            return strtoupper($vars['currency']['code']);
        }
        if (isset($vars['_LANG']['currency']) && is_string($vars['_LANG']['currency']) && $vars['_LANG']['currency'] !== '') {
            return strtoupper($vars['_LANG']['currency']);
        }

        return '';
    }
}

if (!function_exists('meta_pixel_get_purchase_value')) {
    function meta_pixel_get_purchase_value($vars, $settings)
    {
        $source = (string) ($settings['purchase_value_source'] ?? 'auto');

        $pickNumeric = function ($candidate) {
            if ($candidate === null) {
                return null;
            }
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
            // Strip common currency formatting
            if (is_string($candidate)) {
                $str = preg_replace('/[^0-9.\-]/', '', $candidate);
                if ($str !== '' && is_numeric($str)) {
                    return (float) $str;
                }
            }
            return null;
        };

        $cartTotal = $pickNumeric($vars['carttotal'] ?? null);
        $orderTotal = $pickNumeric($vars['ordertotal'] ?? null);
        $invoiceTotal = $pickNumeric($vars['invoice']['total'] ?? ($vars['invoicetotal'] ?? null));

        switch ($source) {
            case 'carttotal':
                return $cartTotal;
            case 'ordertotal':
                return $orderTotal;
            case 'invoice':
                return $invoiceTotal;
            case 'auto':
            default:
                // Prefer invoice total if present, else order total, else cart total.
                return $invoiceTotal ?? $orderTotal ?? $cartTotal;
        }
    }
}

// Load settings once per request
add_hook('ClientAreaPage', 1, function ($vars) {
    $settings = meta_pixel_load_settings();

    $pixelId = meta_pixel_normalize_pixel_id($settings['pixel_id'] ?? '3244586562373140');

    // Map yes/no settings to hook locations
    $hookLocations = [];
    if (meta_pixel_setting_on($settings, 'hook_locations_head', true)) {
        $hookLocations[] = 'ClientAreaHeadOutput';
    }
    if (meta_pixel_setting_on($settings, 'hook_locations_header')) {
        $hookLocations[] = 'ClientAreaHeaderOutput';
    }
    if (meta_pixel_setting_on($settings, 'hook_locations_footer')) {
        $hookLocations[] = 'ClientAreaFooterOutput';
    }
    if (meta_pixel_setting_on($settings, 'hook_locations_product')) {
        $hookLocations[] = 'ClientAreaProductDetailsOutput';
    }
    if (meta_pixel_setting_on($settings, 'hook_locations_checkout')) {
        $hookLocations[] = 'ShoppingCartCheckoutOutput';
    }

    // If no hooks are enabled, default to head
    if (empty($hookLocations)) {
        $hookLocations[] = 'ClientAreaHeadOutput';
    }

    // Store for other hooks
    $GLOBALS['meta_pixel_settings'] = $settings;
    $GLOBALS['meta_pixel_id'] = $pixelId;
    $GLOBALS['meta_pixel_hooks'] = $hookLocations;
    $GLOBALS['meta_pixel_injected'] = false;
});

/**
 * Output core pixel once (even if multiple locations are enabled).
 */
if (!function_exists('meta_pixel_render_base_code')) {
    function meta_pixel_render_base_code()
    {
        $pixelId = $GLOBALS['meta_pixel_id'] ?? '';
        if ($pixelId === '') {
            return '';
        }

        // Prevent duplicate injection when user enables multiple hook locations.
        if (!empty($GLOBALS['meta_pixel_injected'])) {
            return '';
        }
        $GLOBALS['meta_pixel_injected'] = true;

        $pixelIdJs = meta_pixel_js_string($pixelId);

        return <<<HTML
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
 n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
 n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
 t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
 'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', {$pixelIdJs});
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1" /></noscript>
<!-- End Meta Pixel Code -->
HTML;
    }
}

// Standard Meta Pixel code for head, header and footer
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    if (!isset($GLOBALS['meta_pixel_hooks']) || !in_array('ClientAreaHeadOutput', (array) $GLOBALS['meta_pixel_hooks'], true)) {
        return '';
    }
    return meta_pixel_render_base_code();
});

add_hook('ClientAreaHeaderOutput', 1, function ($vars) {
    if (!isset($GLOBALS['meta_pixel_hooks']) || !in_array('ClientAreaHeaderOutput', (array) $GLOBALS['meta_pixel_hooks'], true)) {
        return '';
    }
    return meta_pixel_render_base_code();
});

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    if (!isset($GLOBALS['meta_pixel_hooks']) || !in_array('ClientAreaFooterOutput', (array) $GLOBALS['meta_pixel_hooks'], true)) {
        return '';
    }
    return meta_pixel_render_base_code();
});

// ViewContent event tracking for product details
add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    if (!isset($GLOBALS['meta_pixel_hooks']) || !in_array('ClientAreaProductDetailsOutput', (array) $GLOBALS['meta_pixel_hooks'], true)) {
        return '';
    }

    $productName = $vars['productinfo']['name'] ?? '';
    $productGroupName = $vars['productinfo']['groupname'] ?? '';
    $pid = $vars['productinfo']['pid'] ?? '';

    $nameJs = meta_pixel_js_string($productName);
    $categoryJs = meta_pixel_js_string($productGroupName);
    $pidJs = meta_pixel_js_string($pid);

    return <<<HTML
<script>
if (typeof fbq === 'function') {
  fbq('track', 'ViewContent', {
    content_name: {$nameJs},
    content_category: {$categoryJs},
    content_ids: [{$pidJs}],
    content_type: 'product'
  });
}
</script>
HTML;
});

// InitiateCheckout event tracking
add_hook('ShoppingCartCheckoutOutput', 1, function ($vars) {
    if (!isset($GLOBALS['meta_pixel_hooks']) || !in_array('ShoppingCartCheckoutOutput', (array) $GLOBALS['meta_pixel_hooks'], true)) {
        return '';
    }

    return <<<HTML
<script>
if (typeof fbq === 'function') {
  fbq('track', 'InitiateCheckout');
}
</script>
HTML;
});

// Purchase event tracking on order completion / receipt pages
add_hook('ClientAreaPage', 2, function ($vars) {
    $settings = $GLOBALS['meta_pixel_settings'] ?? [];
    if (!meta_pixel_setting_on($settings, 'track_purchase', true)) {
        return;
    }

    // Only attempt on cart order complete / view invoice contexts.
    $filename = (string) ($vars['filename'] ?? '');

    // Heuristic: WHMCS cart order complete is typically cart.php?a=complete
    $isCartComplete = ($filename === 'cart') && (($_REQUEST['a'] ?? '') === 'complete');
    $isViewInvoice = ($filename === 'viewinvoice');

    if (!$isCartComplete && !$isViewInvoice) {
        return;
    }

    // Derive value/currency if possible.
    $value = meta_pixel_get_purchase_value($vars, $settings);
    $currency = meta_pixel_get_currency($vars, $settings);

    $valuePart = '';
    if ($value !== null) {
        $valuePart .= "value: " . json_encode(round((float)$value, 2)) . ",";
    }
    if ($currency !== '') {
        $valuePart .= "currency: " . meta_pixel_js_string($currency) . ",";
    }

    $payload = trim($valuePart);
    $payload = rtrim($payload, ',');
    $payload = $payload !== '' ? "{\n  {$payload}\n}" : "{}";

    $script = <<<HTML
<script>
(function(){
  if (typeof fbq !== 'function') return;
  try {
    fbq('track', 'Purchase', {$payload});
  } catch (e) {}
})();
</script>
HTML;

    // Append to footer by default, or current hook output is not guaranteed. Use global to be picked up by footer output.
    $GLOBALS['meta_pixel_purchase_script'] = ($GLOBALS['meta_pixel_purchase_script'] ?? '') . $script;
});

// Emit any deferred scripts (Purchase) in footer if pixel is present anywhere.
add_hook('ClientAreaFooterOutput', 9999, function ($vars) {
    $out = '';
    if (!empty($GLOBALS['meta_pixel_purchase_script'])) {
        $out .= $GLOBALS['meta_pixel_purchase_script'];
        $GLOBALS['meta_pixel_purchase_script'] = '';
    }
    return $out;
});

