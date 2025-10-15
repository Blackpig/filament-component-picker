# Filament Component Picker

A powerful FilamentPHP package that provides a component picker for RichEditor fields with shortcode rendering support. Insert Blade components into rich text content using a visual picker, and render them on your frontend using shortcode syntax.

## Features

- 🎨 **Visual Component Picker** - Add a button to any RichEditor field to insert components
- 🔍 **Auto-discovery** - Automatically discovers components from configured directories
- 📝 **Shortcode Rendering** - Parse and render `[component-name attr="value"]` shortcodes on the frontend
- 🎯 **Smart Type Detection** - Automatically detects component prop types (text, URL, KeyValue, nested objects)
- 🎨 **CSS Class Merging** - Supports components with `$attributes->merge()` for custom styling
- ⚙️ **Highly Configurable** - Customize directories, labels, exclusions, and more

## Installation

Since this package is not yet available on Packagist, you'll need to install it directly from the GitHub repository.

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/blackpig-creatif/filament-component-picker"
        }
    ]
}
```

Then install the package via composer:

```bash
composer require blackpig-creatif/filament-component-picker:dev-main
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="blackpig-creatif-component-picker-config"
```

This is the contents of the published config file:

```php
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
```

## Usage

### 1. Create Your Components

Create Blade components in `resources/views/components/richeditor/`:

```blade
{{-- resources/views/components/richeditor/attribution.blade.php --}}
@props([
    'photographer' => '',
    'link' => ''
])

<div {{ $attributes->merge(['class' => 'text-sm text-gray-600 italic']) }}>
    Photo by <a href="{{ $link }}" target="_blank" class="underline">{{ $photographer }}</a>
</div>
```

```blade
{{-- resources/views/components/richeditor/callout.blade.php --}}
@props([
    'title' => '',
    'content' => '',
    'type' => 'info'
])

<div class="callout callout-{{ $type }}">
    <h4>{{ $title }}</h4>
    <p>{{ $content }}</p>
</div>
```

### 2. Add Component Picker to Your Filament Resource

```php
<?php

namespace App\Filament\Resources;

use BlackpigCreatif\FilamentComponentPicker\Actions\ComponentPickerAction;
use Filament\Forms\Components\RichEditor;
use Filament\Resources\Resource;

class BlogPostResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                RichEditor::make('content')
                    ->required()
                    ->hintAction(
                        ComponentPickerAction::make('insertComponent')
                            // Auto-discovers components from richeditor/ directory
                            // Optionally add more from other directories:
                            ->addOptions(['shared.cta-button', 'shared.cta-icon'])
                    ),
            ]);
    }
}
```

### 3. Render Shortcodes on the Frontend

```blade
{{-- resources/views/blog/show.blade.php --}}
@php
    use BlackpigCreatif\FilamentComponentPicker\Services\ShortcodeParser;
@endphp

<article>
    <h1>{{ $post->title }}</h1>

    <div class="content">
        {!! ShortcodeParser::parse($post->content) !!}
    </div>
</article>
```

### Advanced Usage

#### Exclude Specific Components

```php
ComponentPickerAction::make('insertComponent')
    ->excludeOptions(['internal-component', 'deprecated-widget'])
```

#### Exclude All Auto-discovered Components

```php
ComponentPickerAction::make('insertComponent')
    ->withoutAutoDiscovery()
    ->addOptions(['shared.cta-button']) // Only show these
```

#### Use Custom Exclusion Logic

```php
ComponentPickerAction::make('insertComponent')
    ->excludeOptions(function (array $options) {
        // Remove all components starting with 'admin-'
        return array_filter($options, fn($key) => !str_starts_with($key, 'admin-'), ARRAY_FILTER_USE_KEY);
    })
```

#### Custom Component Labels

In your config file:

```php
'component_labels' => [
    'attribution' => 'Photo Attribution',
    'cta-button' => 'Call to Action Button',
],
```

## Component Folder Structure

The package uses a hierarchical component resolution system:

```
resources/views/components/
├── richeditor/              # Default directory (auto-discovered)
│   ├── attribution.blade.php
│   ├── callout.blade.php
│   └── quote.blade.php
├── shared/                  # Explicitly added components
│   ├── cta-button.blade.php
│   └── cta-icon.blade.php
└── marketing/
    └── newsletter.blade.php
```

**Usage in Component Picker:**

- `attribution` → looks in `richeditor/`, then `components/`
- `shared.cta-button` → looks in `shared/`, then `components/`
- `marketing.newsletter` → looks in `marketing/`, then `components/`

## How It Works

### In the Admin Panel

1. Click the component picker button in any RichEditor field
2. Select a component from the dropdown
3. Fill in the component's props in the generated form
4. The shortcode is inserted: `[component-name prop1="value1" prop2="value2"]`

### On the Frontend

1. ShortcodeParser finds all `[component-name ...]` patterns
2. Validates the component exists and is whitelisted
3. Parses attributes (including JSON for complex props)
4. Renders the component with the provided attributes

### Smart Type Detection

The package automatically detects component prop types:

- **KeyValue**: Props named `*_data`, `*_config`, `*_meta`
- **Nested Objects**: Props named `*_settings`, `*_options`, `*_params`
- **URLs**: Props named `*_url`, `*_link`, `link`
- **Text**: Everything else (TextInput or Textarea based on name)

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Blackpig](https://github.com/blackpig)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
