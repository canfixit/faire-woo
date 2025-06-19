# FaireWoo Sync System Extensibility

This document outlines the available hooks and filters for extending the FaireWoo sync system.

## Filters

### Pre-Sync Filters

#### `faire_woo_pre_sync_order`
Controls whether an order should be synchronized.

**Parameters:**
- `$should_sync` (bool) Default true
- `$wc_order_id` (int) WooCommerce order ID

**Example:**
```php
add_filter('faire_woo_pre_sync_order', function($should_sync, $wc_order_id) {
    // Skip sync for orders with specific meta
    if (get_post_meta($wc_order_id, '_skip_faire_sync', true)) {
        return false;
    }
    return $should_sync;
}, 10, 2);
```

### State Management Filters

#### `faire_woo_initial_sync_state`
Modifies the initial state when starting sync.

**Parameters:**
- `$state` (string) Default 'syncing'
- `$wc_order_id` (int) WooCommerce order ID

#### `faire_woo_state_transition_metadata`
Modifies metadata for state transitions.

**Parameters:**
- `$metadata` (array) State metadata
- `$wc_order_id` (int) WooCommerce order ID
- `$state` (string) New state

### Sync Process Filters

#### `faire_woo_compare_orders`
Modifies order comparison results.

**Parameters:**
- `$comparison_result` (array) Comparison result
- `$wc_order_id` (int) WooCommerce order ID
- `$faire_order_id` (string) Faire order ID

#### `faire_woo_handle_conflict`
Customizes conflict handling behavior.

**Parameters:**
- `$action` (string) Default 'set_conflict_state'
- `$wc_order_id` (int) WooCommerce order ID
- `$conflicts` (array) Detected conflicts

#### `faire_woo_success_state`
Modifies the success state.

**Parameters:**
- `$state` (string) Default 'synced'
- `$wc_order_id` (int) WooCommerce order ID

#### `faire_woo_error_state`
Modifies the error state.

**Parameters:**
- `$state` (string) Default 'failed'
- `$wc_order_id` (int) WooCommerce order ID
- `$exception` (Exception) The thrown exception

## Actions

### `faire_woo_after_sync_success`
Triggered after successful synchronization.

**Parameters:**
- `$wc_order_id` (int) WooCommerce order ID

### `faire_woo_sync_error`
Triggered when sync encounters an error.

**Parameters:**
- `$wc_order_id` (int) WooCommerce order ID
- `$exception` (Exception) The thrown exception

## Example Extensions

### Custom Conflict Resolution
```php
// Implement custom conflict resolution
add_filter('faire_woo_handle_conflict', function($action, $wc_order_id, $conflicts) {
    // Check if conflicts can be auto-resolved
    if (can_auto_resolve_conflicts($conflicts)) {
        return 'auto_resolve';
    }
    return $action;
}, 10, 3);
```

### Extended Error Handling
```php
// Add Slack notifications for sync errors
add_action('faire_woo_sync_error', function($wc_order_id, $exception) {
    send_slack_notification([
        'channel' => '#faire-sync-errors',
        'message' => sprintf(
            'Sync failed for order #%d: %s',
            $wc_order_id,
            $exception->getMessage()
        )
    ]);
}, 10, 2);
```

### Custom State Metadata
```php
// Add additional metadata during state transitions
add_filter('faire_woo_state_transition_metadata', function($metadata, $wc_order_id, $state) {
    $metadata['user_id'] = get_current_user_id();
    $metadata['environment'] = wp_get_environment_type();
    return $metadata;
}, 10, 3);
```

## Best Practices

1. **Priority Levels**
   - Use priority 5-9 for early modifications
   - Use priority 11-20 for late modifications
   - Default WordPress priority is 10

2. **Error Handling**
   - Always return the expected data type
   - Catch and log any exceptions in your callbacks
   - Don't break the sync process

3. **Performance**
   - Keep filters lightweight
   - Cache expensive operations
   - Use appropriate hook priorities

4. **Compatibility**
   - Check for required functions/classes
   - Version your extensions
   - Document dependencies 