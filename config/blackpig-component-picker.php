<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Component Directory
    |--------------------------------------------------------------------------
    |
    | The default directory where component picker will look for components.
    | This path is relative to resources/views/components/
    |
    | Default: 'richeditor'
    | Example: components/richeditor/attribution.blade.php
    |
    */

    'default_directory' => env('COMPONENT_PICKER_DEFAULT_DIR', 'richeditor'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Directory
    |--------------------------------------------------------------------------
    |
    | If a component is not found in the default directory, the picker will
    | fall back to this directory.
    |
    | Default: null (will search in components root)
    | Example: 'shared' would search in components/shared/
    |
    */

    'fallback_directory' => env('COMPONENT_PICKER_FALLBACK_DIR', null),

    /*
    |--------------------------------------------------------------------------
    | Auto-discover Components
    |--------------------------------------------------------------------------
    |
    | Automatically discover all components in the default directory and
    | make them available in the picker without explicit configuration.
    |
    */

    'auto_discover' => env('COMPONENT_PICKER_AUTO_DISCOVER', true),

    /*
    |--------------------------------------------------------------------------
    | Default Options
    |--------------------------------------------------------------------------
    |
    | Components that should always be available in the picker.
    | These are added to auto-discovered components.
    |
    | Use dot notation for nested components:
    | - 'attribution' will look in: richeditor/, then components/
    | - 'shared.attribution' will look in: shared/, then components/
    | - 'marketing.cta.button' will look in: marketing/cta/, then components/
    |
    */

    'default_options' => [
        // 'shared.cta-button',
        // 'shared.cta-icon',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Components
    |--------------------------------------------------------------------------
    |
    | Components to exclude from auto-discovery.
    | Use simple names (without directory prefix).
    |
    */

    'excluded_components' => [
        // 'internal-component',
        // 'deprecated-widget',
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Labels
    |--------------------------------------------------------------------------
    |
    | Custom labels for components in the picker dropdown.
    | If not specified, labels are auto-generated from component names.
    |
    | Example: 'cta-button' becomes 'Cta Button'
    |
    */

    'component_labels' => [
        // 'attribution' => 'Photo Attribution',
        // 'cta-button' => 'Call to Action Button',
    ],

    /*
    |--------------------------------------------------------------------------
    | Shortcode Parser
    |--------------------------------------------------------------------------
    |
    | Configuration for the shortcode parser that renders components
    | on the frontend.
    |
    */

    'parser' => [
        // Cache parsed component configurations
        'cache' => env('COMPONENT_PICKER_CACHE', true),

        // Cache duration in seconds (default: 1 hour)
        'cache_duration' => 3600,

        // Log parsing errors
        'log_errors' => env('COMPONENT_PICKER_LOG_ERRORS', true),
    ],

];
