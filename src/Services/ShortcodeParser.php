<?php

namespace Blackpig\FilamentComponentPicker\Services;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ShortcodeParser
{
    /**
     * Whitelisted components that can be rendered via shortcodes
     */
    protected static array $allowedComponents = [];

    /**
     * Parse content and replace shortcodes with rendered components
     */
    public static function parse(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Match shortcodes: [component-name attr1="value1" attr2="value2"]
        $pattern = '/\[([a-z\-\.]+)([^\]]*)\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            $componentName = $matches[1];
            $attributesString = $matches[2] ?? '';

            // Auto-register component if not already registered
            if (!isset(self::$allowedComponents[$componentName])) {
                self::autoRegisterComponent($componentName);
            }

            // Check if component is whitelisted
            if (!isset(self::$allowedComponents[$componentName])) {
                return $matches[0]; // Return original shortcode if not whitelisted
            }

            $componentConfig = self::$allowedComponents[$componentName];
            $attributes = self::parseAttributes($attributesString);

            // Validate component view exists
            if (!View::exists($componentConfig['path'])) {
                return $matches[0]; // Return original shortcode if view doesn't exist
            }

            try {
                return self::renderComponent($componentName, $componentConfig, $attributes);
            } catch (\Exception $e) {
                // Log error and return original shortcode
                \Log::error('Shortcode parsing error', [
                    'component' => $componentName,
                    'error' => $e->getMessage(),
                ]);
                return $matches[0];
            }
        }, $content);
    }

    /**
     * Parse attribute string into key-value array
     */
    protected static function parseAttributes(string $attributesString): array
    {
        $attributes = [];

        // Match: key="value" or key='value' (handles JSON in single quotes)
        preg_match_all('/([a-z\-_]+)=(["\'])([^\2]*?)\2/i', $attributesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[3];

            // Decode JSON if value looks like JSON
            if (Str::startsWith($value, ['{', '['])) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Render component with given attributes
     */
    protected static function renderComponent(string $componentName, array $config, array $attributes): string
    {
        $viewPath = $config['path'];

        // Extract class attribute if present (for merging)
        $class = $attributes['class'] ?? null;
        unset($attributes['class']);

        // Pass attributes directly to the view
        // The component's @props directive will handle the structure
        $data = $attributes;

        // Pass class as component attributes for merging
        $componentAttributes = $class ? ['class' => $class] : [];

        return view($viewPath, $data)
            ->with('attributes', new \Illuminate\View\ComponentAttributeBag($componentAttributes))
            ->render();
    }

    /**
     * Get list of allowed components for UI selection
     */
    public static function getAllowedComponents(): array
    {
        return array_keys(self::$allowedComponents);
    }

    /**
     * Get component configuration
     */
    public static function getComponentConfig(string $componentName): ?array
    {
        return self::$allowedComponents[$componentName] ?? null;
    }

    /**
     * Register a new component for shortcode parsing
     */
    public static function registerComponent(string $name, string $viewPath, array $attributes = []): void
    {
        self::$allowedComponents[$name] = [
            'path' => $viewPath,
            'attributes' => $attributes,
        ];
    }

    /**
     * Auto-register component by inferring path from name
     */
    protected static function autoRegisterComponent(string $name): void
    {
        // If name doesn't contain dots, assume it's in shared components
        if (!Str::contains($name, '.')) {
            $viewPath = "components.shared.{$name}";
        } else {
            $viewPath = $name;
        }

        // Check if view exists before registering
        if (View::exists($viewPath)) {
            self::registerComponent($name, $viewPath);
        }
    }
}
