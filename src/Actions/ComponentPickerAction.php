<?php

namespace Blackpig\FilamentComponentPicker\Actions;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ComponentPickerAction extends Action
{
    protected array $componentOptions = [];

    protected array $componentConfigs = [];

    protected array $additionalOptions = [];

    protected array $excludedOptions = [];

    protected bool $excludeAllDiscovered = false;

    protected $excludeCallback = null;

    public static function getDefaultName(): ?string
    {
        return 'insertComponent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Insert Component')
            ->icon('heroicon-o-puzzle-piece')
            ->modalWidth('md')
            ->modalHeading('Insert Component Shortcode')
            ->modalSubmitActionLabel('Insert');

        $this->form(function () {
            // Initialize options when form is built
            $this->initializeOptions();

            return $this->buildForm();
        });

        $this->action(function (array $data, Set $set, Get $get) {
            $this->handleInsertion($data, $set, $get);
        });
    }

    /**
     * Initialize component options from config and auto-discovery
     */
    protected function initializeOptions(): void
    {
        if (empty($this->componentOptions)) {
            $discovered = $this->autoDiscoverComponents();
            $default = config('blackpig-component-picker.default_options', []);
            $excluded = config('blackpig-component-picker.excluded_components', []);

            // Handle exclude all discovered
            if ($this->excludeAllDiscovered) {
                $allOptions = array_unique(array_merge($default, $this->additionalOptions));
            } else {
                // Merge discovered and default options
                $allOptions = array_unique(array_merge($discovered, $default, $this->additionalOptions));
            }

            // Remove excluded components
            $excluded = array_merge($excluded, $this->excludedOptions);
            $allOptions = array_diff($allOptions, $excluded);

            // Apply callback filter if provided
            if (is_callable($this->excludeCallback)) {
                $allOptions = array_filter($allOptions, function ($option) {
                    return ! call_user_func($this->excludeCallback, $option);
                });
            }

            $this->componentOptions = array_values($allOptions);

            // Parse and cache component configurations
            foreach ($this->componentOptions as $option) {
                $this->componentConfigs[$option] = $this->parseComponent($option);
            }
        }
    }

    /**
     * Set available component options (replaces auto-discovery)
     */
    public function options(array $options): static
    {
        // Set options and prevent re-initialization
        $this->componentOptions = $options;
        $this->componentConfigs = []; // Clear existing configs

        // Parse and cache component configurations
        foreach ($options as $option) {
            $this->componentConfigs[$option] = $this->parseComponent($option);
        }

        return $this;
    }

    /**
     * Add components to the default options
     */
    public function addOptions(array $options): static
    {
        $this->additionalOptions = array_merge($this->additionalOptions, $options);
        $this->componentOptions = []; // Reset to trigger re-initialization

        return $this;
    }

    /**
     * Exclude components from the options
     *
     * @param  array|bool|\Closure  $options
     *                                        - array: Specific components to exclude ['component1', 'component2']
     *                                        - true: Exclude ALL auto-discovered components (keeps only config defaults + addOptions)
     *                                        - Closure: Callback to filter components fn(string $componentName): bool (return true to exclude)
     */
    public function excludeOptions(array | bool | \Closure $options = true): static
    {
        if ($options === true) {
            // Exclude all auto-discovered components
            $this->excludeAllDiscovered = true;
        } elseif (is_array($options)) {
            // Exclude specific components
            $this->excludedOptions = array_merge($this->excludedOptions, $options);
        } elseif (is_callable($options)) {
            // Set callback filter
            $this->excludeCallback = $options;
        }

        $this->componentOptions = []; // Reset to trigger re-initialization

        return $this;
    }

    /**
     * Convenience method: Exclude all auto-discovered components
     * Only uses config defaults and manually added options
     */
    public function withoutAutoDiscovery(): static
    {
        return $this->excludeOptions(true);
    }

    /**
     * Convenience method: Exclude components matching a pattern
     */
    public function excludePattern(string $pattern): static
    {
        return $this->excludeOptions(function ($component) use ($pattern) {
            return Str::is($pattern, $component);
        });
    }

    /**
     * Auto-discover components in default directory
     */
    protected function autoDiscoverComponents(): array
    {
        if (! config('blackpig-component-picker.auto_discover', true)) {
            return [];
        }

        $defaultDir = config('blackpig-component-picker.default_directory', 'richeditor');
        $path = resource_path("views/components/{$defaultDir}");

        if (! File::isDirectory($path)) {
            return [];
        }

        $components = [];
        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $name = $file->getFilenameWithoutExtension();
                // Remove .blade suffix if present
                $name = Str::replaceLast('.blade', '', $name);
                $components[] = $name;
            }
        }

        return $components;
    }

    /**
     * Build dynamic form based on component selection
     */
    protected function buildForm(): array
    {
        if (empty($this->componentOptions)) {
            return [];
        }

        $formFields = [
            Select::make('component')
                ->label('Component Type')
                ->options($this->buildComponentLabels())
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    // Reset all possible fields when component changes
                    foreach ($this->getAllPossibleFields() as $field) {
                        $set($field, '');
                    }
                }),
        ];

        // Add dynamic fields for each component
        foreach ($this->componentConfigs as $componentName => $config) {
            if (isset($config['props'])) {
                foreach ($config['props'] as $prop => $propConfig) {
                    $fields = $this->buildFieldForProp($componentName, $prop, $propConfig);

                    // Flatten array of fields if nested
                    if (is_array($fields) && isset($fields[0])) {
                        $formFields = array_merge($formFields, $fields);
                    } else {
                        $formFields[] = $fields;
                    }
                }
            }

            // Add optional class field if component supports it
            if ($config['supports_class_merge'] ?? false) {
                $formFields[] = TextInput::make("{$componentName}_class")
                    ->label('CSS Classes')
                    ->helperText('Optional classes to merge with component defaults')
                    ->placeholder('e.g., bg-blue-500 text-white')
                    ->visible(fn (Get $get) => $get('component') === $componentName);
            }
        }

        return $formFields;
    }

    /**
     * Build human-readable labels from component names
     */
    protected function buildComponentLabels(): array
    {
        $customLabels = config('blackpig-component-picker.component_labels', []);
        $labels = [];

        foreach ($this->componentOptions as $option) {
            // Use custom label if configured
            if (isset($customLabels[$option])) {
                $labels[$option] = $customLabels[$option];
            } else {
                // Auto-generate label from component name
                // Remove directory prefix if present
                $name = Str::contains($option, '.')
                    ? Str::afterLast($option, '.')
                    : $option;

                $labels[$option] = Str::of($name)
                    ->replace('-', ' ')
                    ->replace('_', ' ')
                    ->title()
                    ->toString();
            }
        }

        return $labels;
    }

    /**
     * Parse Blade component to extract props and their types
     */
    protected function parseComponent(string $componentName): array
    {
        $viewPath = $this->resolveComponentPath($componentName);

        if (! File::exists($viewPath)) {
            return ['props' => [], 'supports_class_merge' => false];
        }

        $content = File::get($viewPath);
        $props = $this->extractProps($content);
        $usedVariables = $this->extractUsedVariables($content);
        $supportsClassMerge = $this->detectClassMergeSupport($content);

        return [
            'props' => $this->inferPropTypes($props, $usedVariables, $content),
            'path' => $viewPath,
            'supports_class_merge' => $supportsClassMerge,
        ];
    }

    /**
     * Resolve component name to file path with hierarchical search
     */
    protected function resolveComponentPath(string $componentName): string
    {
        $defaultDir = config('blackpig-component-picker.default_directory', 'richeditor');
        $fallbackDir = config('blackpig-component-picker.fallback_directory');

        // If component has dots, it's an explicit path (e.g., 'shared.attribution')
        if (Str::contains($componentName, '.')) {
            $segments = explode('.', $componentName);
            $fileName = array_pop($segments);
            $directory = implode('/', $segments);

            // Try explicit path first: components/{directory}/{fileName}
            $explicitPath = resource_path("views/components/{$directory}/{$fileName}.blade.php");
            if (File::exists($explicitPath)) {
                return $explicitPath;
            }

            // Fallback to components root
            $rootPath = resource_path("views/components/{$fileName}.blade.php");
            if (File::exists($rootPath)) {
                return $rootPath;
            }
        } else {
            // No dots - search in order: default dir -> fallback dir -> components root

            // 1. Try default directory (e.g., richeditor/)
            $defaultPath = resource_path("views/components/{$defaultDir}/{$componentName}.blade.php");
            if (File::exists($defaultPath)) {
                return $defaultPath;
            }

            // 2. Try fallback directory if configured
            if ($fallbackDir) {
                $fallbackPath = resource_path("views/components/{$fallbackDir}/{$componentName}.blade.php");
                if (File::exists($fallbackPath)) {
                    return $fallbackPath;
                }
            }

            // 3. Try components root
            $rootPath = resource_path("views/components/{$componentName}.blade.php");
            if (File::exists($rootPath)) {
                return $rootPath;
            }
        }

        // Return first attempted path if none found (for error handling)
        if (Str::contains($componentName, '.')) {
            $path = str_replace('.', '/', $componentName);

            return resource_path("views/components/{$path}.blade.php");
        }

        return resource_path("views/components/{$defaultDir}/{$componentName}.blade.php");
    }

    /**
     * Extract @props directive from Blade file
     */
    protected function extractProps(string $content): array
    {
        if (preg_match('/@props\(\[(.*?)\]\)/s', $content, $matches)) {
            $propsString = $matches[1];

            // Parse array keys from @props(['key1', 'key2' => 'default'])
            preg_match_all('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]/', $propsString, $propMatches);

            return $propMatches[1] ?? [];
        }

        return [];
    }

    /**
     * Extract variables used in the template
     */
    protected function extractUsedVariables(string $content): array
    {
        $variables = [];

        // Match $variable['key'] or $variable->key patterns
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\[[\'"](.*?)[\'"]\]/', $content, $arrayMatches);
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $objectMatches);

        // Store array access patterns
        if (! empty($arrayMatches[1])) {
            foreach ($arrayMatches[1] as $index => $varName) {
                $key = $arrayMatches[2][$index];
                if (! isset($variables[$varName])) {
                    $variables[$varName] = [];
                }
                $variables[$varName][] = $key;
            }
        }

        // Store object access patterns
        if (! empty($objectMatches[1])) {
            foreach ($objectMatches[1] as $index => $varName) {
                $key = $objectMatches[2][$index];
                if (! isset($variables[$varName])) {
                    $variables[$varName] = [];
                }
                $variables[$varName][] = $key;
            }
        }

        return $variables;
    }

    /**
     * Detect if prop is used as associative array (key-value pair)
     */
    protected function isKeyValueArray(string $prop, string $content): bool
    {
        // Check for array functions that indicate key-value usage
        $patterns = [
            '/key\s*\(\s*\$' . $prop . '\s*\)/',
            '/reset\s*\(\s*\$' . $prop . '\s*\)/',
            '/array_keys\s*\(\s*\$' . $prop . '\s*\)/',
            '/array_values\s*\(\s*\$' . $prop . '\s*\)/',
            '/@foreach\s*\(\s*\$' . $prop . '\s+as\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*=>\s*\$[a-zA-Z_][a-zA-Z0-9_]*/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if prop is used as indexed/list array
     */
    protected function isListArray(string $prop, string $content): bool
    {
        // Check for foreach without key or array access without specific keys
        $patterns = [
            '/@foreach\s*\(\s*\$' . $prop . '\s+as\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*\)(?!\s*=>)/',
            '/count\s*\(\s*\$' . $prop . '\s*\)/',
            '/empty\s*\(\s*\$' . $prop . '\s*\)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if component supports class merging
     */
    protected function detectClassMergeSupport(string $content): bool
    {
        // Check for $attributes->merge(['class'
        if (preg_match('/\$attributes\s*->\s*merge\s*\(\s*\[\s*[\'"]class[\'"]/i', $content)) {
            return true;
        }

        // Check for @class directive
        if (preg_match('/@class\s*\(/i', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Infer prop types based on usage patterns
     */
    protected function inferPropTypes(array $props, array $usedVariables, string $content): array
    {
        $typedProps = [];

        foreach ($props as $prop) {
            $propConfig = [
                'type' => 'text',
                'required' => true,
                'subfields' => [],
            ];

            // Priority 1: Check for key-value array usage (like attribution component)
            if ($this->isKeyValueArray($prop, $content)) {
                $propConfig['type'] = 'keyvalue';
            }
            // Priority 2: Check if prop has nested structure (array or object with specific keys)
            elseif (isset($usedVariables[$prop]) && ! empty($usedVariables[$prop])) {
                $propConfig['type'] = 'nested';
                $propConfig['subfields'] = array_unique($usedVariables[$prop]);
            }
            // Priority 3: Check for list/indexed array
            elseif ($this->isListArray($prop, $content)) {
                $propConfig['type'] = 'repeater';
            }
            // Priority 4: Detect URL fields by name
            elseif (Str::contains($prop, ['url', 'link', 'href'])) {
                $propConfig['type'] = 'url';
            }

            $typedProps[$prop] = $propConfig;
        }

        return $typedProps;
    }

    /**
     * Build form field for a specific prop
     */
    protected function buildFieldForProp(string $componentName, string $prop, array $config): mixed
    {
        $fieldName = "{$componentName}_{$prop}";

        // Handle key-value array (associative array)
        if ($config['type'] === 'keyvalue') {
            return KeyValue::make($fieldName)
                ->label(Str::of($prop)->replace('_', ' ')->title()->toString())
                ->required($config['required'] ?? true)
                ->visible(fn (Get $get) => $get('component') === $componentName)
                ->addable()
                ->reorderable(false)
                ->keyLabel('Key')
                ->valueLabel('Value');
        }

        // Handle nested properties (e.g., cta array with link and label)
        if ($config['type'] === 'nested' && ! empty($config['subfields'])) {
            $fields = [];
            foreach ($config['subfields'] as $subfield) {
                $subfieldName = "{$componentName}_{$prop}_{$subfield}";
                $fields[] = $this->buildTextField($subfieldName, $subfield, $componentName);
            }

            return $fields;
        }

        // Single field
        return $this->buildTextField($fieldName, $prop, $componentName, $config);
    }

    /**
     * Build a text input field
     */
    protected function buildTextField(string $fieldName, string $label, string $componentName, array $config = []): TextInput
    {
        $field = TextInput::make($fieldName)
            ->label(Str::of($label)->replace('_', ' ')->title()->toString())
            ->required($config['required'] ?? true)
            ->visible(fn (Get $get) => $get('component') === $componentName);

        // Add URL validation if detected
        if (($config['type'] ?? 'text') === 'url' || Str::contains($label, ['url', 'link', 'href'])) {
            $field->url();
        }

        return $field;
    }

    /**
     * Get all possible field names across all components
     */
    protected function getAllPossibleFields(): array
    {
        $fields = [];

        foreach ($this->componentConfigs as $componentName => $config) {
            if (isset($config['props'])) {
                foreach ($config['props'] as $prop => $propConfig) {
                    if ($propConfig['type'] === 'nested' && ! empty($propConfig['subfields'])) {
                        foreach ($propConfig['subfields'] as $subfield) {
                            $fields[] = "{$componentName}_{$prop}_{$subfield}";
                        }
                    } else {
                        $fields[] = "{$componentName}_{$prop}";
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Handle shortcode insertion
     */
    protected function handleInsertion(array $data, Set $set, Get $get): void
    {
        $componentName = $data['component'];
        $config = $this->componentConfigs[$componentName] ?? null;

        if (! $config) {
            return;
        }

        $shortcode = $this->buildShortcode($componentName, $data, $config);

        // Get current content and append shortcode
        $currentContent = $get('content') ?? '';
        $set('content', $currentContent . "\n" . $shortcode);
    }

    /**
     * Build shortcode string from component and data
     */
    protected function buildShortcode(string $componentName, array $data, array $config): string
    {
        $attributes = [];

        // Extract relevant attributes for this component
        foreach ($config['props'] as $prop => $propConfig) {
            $fieldName = "{$componentName}_{$prop}";

            // Handle key-value array
            if ($propConfig['type'] === 'keyvalue') {
                if (! empty($data[$fieldName]) && is_array($data[$fieldName])) {
                    // KeyValue component returns array - encode as JSON
                    $attributes[] = sprintf('%s=\'%s\'', $prop, json_encode($data[$fieldName], JSON_UNESCAPED_SLASHES));
                }
            }
            // Handle nested structure
            elseif ($propConfig['type'] === 'nested' && ! empty($propConfig['subfields'])) {
                $nestedValues = [];
                foreach ($propConfig['subfields'] as $subfield) {
                    $subfieldName = "{$componentName}_{$prop}_{$subfield}";
                    if (! empty($data[$subfieldName])) {
                        $nestedValues[$subfield] = $data[$subfieldName];
                    }
                }

                // Encode nested structure as JSON
                if (! empty($nestedValues)) {
                    $attributes[] = sprintf('%s=\'%s\'', $prop, json_encode($nestedValues, JSON_UNESCAPED_SLASHES));
                }
            }
            // Handle simple field
            else {
                if (! empty($data[$fieldName])) {
                    $value = $data[$fieldName];

                    // If value is array, encode as JSON
                    if (is_array($value)) {
                        $attributes[] = sprintf('%s=\'%s\'', $prop, json_encode($value, JSON_UNESCAPED_SLASHES));
                    } else {
                        $attributes[] = sprintf('%s="%s"', $prop, htmlspecialchars($value, ENT_QUOTES));
                    }
                }
            }
        }

        // Add class attribute if component supports it and class is provided
        if (($config['supports_class_merge'] ?? false) && ! empty($data["{$componentName}_class"])) {
            $attributes[] = sprintf('class="%s"', htmlspecialchars($data["{$componentName}_class"], ENT_QUOTES));
        }

        return sprintf('[%s %s]', $componentName, implode(' ', $attributes));
    }
}
