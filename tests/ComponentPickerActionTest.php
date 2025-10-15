<?php

use BlackpigCreatif\FilamentComponentPicker\Actions\ComponentPickerAction;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create test component directory
    $componentPath = resource_path('views/components/richeditor');
    File::ensureDirectoryExists($componentPath);

    // Create a test component
    File::put(
        "{$componentPath}/test-component.blade.php",
        <<<'BLADE'
@props([
    'title' => '',
    'content' => '',
    'url' => ''
])

<div {{ $attributes->merge(['class' => 'test-component']) }}>
    <h3>{{ $title }}</h3>
    <p>{{ $content }}</p>
    <a href="{{ $url }}">Link</a>
</div>
BLADE
    );
});

afterEach(function () {
    // Clean up test components
    $componentPath = resource_path('views/components/richeditor');
    if (File::exists($componentPath)) {
        File::deleteDirectory($componentPath);
    }
});

it('can auto-discover components from default directory', function () {
    $action = ComponentPickerAction::make('insertComponent');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('autoDiscoverComponents');
    $method->setAccessible(true);

    $discovered = $method->invoke($action);

    expect($discovered)->toContain('test-component');
});

it('can parse component props correctly', function () {
    $action = ComponentPickerAction::make('insertComponent');

    // Use reflection to access protected method
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('parseComponent');
    $method->setAccessible(true);

    $config = $method->invoke($action, 'test-component');

    expect($config)->toHaveKeys(['props', 'path', 'supports_class_merge'])
        ->and($config['props'])->toHaveKeys(['title', 'content', 'url'])
        ->and($config['props']['url']['type'])->toBe('url')
        ->and($config['supports_class_merge'])->toBeTrue();
});

it('detects class merge support', function () {
    $action = ComponentPickerAction::make('insertComponent');

    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('detectClassMergeSupport');
    $method->setAccessible(true);

    $contentWithMerge = '<div {{ $attributes->merge([\'class\' => \'test\']) }}>';
    $contentWithClass = '<div @class([\'test\' => true])>';
    $contentWithout = '<div class="static-class">';

    expect($method->invoke($action, $contentWithMerge))->toBeTrue()
        ->and($method->invoke($action, $contentWithClass))->toBeTrue()
        ->and($method->invoke($action, $contentWithout))->toBeFalse();
});

it('resolves component paths correctly', function () {
    $action = ComponentPickerAction::make('insertComponent');

    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('resolveComponentPath');
    $method->setAccessible(true);

    $path = $method->invoke($action, 'test-component');

    expect($path)->toContain('richeditor/test-component.blade.php')
        ->and(File::exists($path))->toBeTrue();
});

it('infers URL type from prop name', function () {
    $action = ComponentPickerAction::make('insertComponent');

    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('inferPropTypes');
    $method->setAccessible(true);

    $props = ['link', 'image_url', 'href'];
    $result = $method->invoke($action, $props, [], '');

    expect($result['link']['type'])->toBe('url')
        ->and($result['image_url']['type'])->toBe('url')
        ->and($result['href']['type'])->toBe('url');
});
