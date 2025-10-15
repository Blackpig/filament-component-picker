<?php

use BlackpigCreatif\FilamentComponentPicker\Services\ShortcodeParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    // Create test component directory
    $componentPath = resource_path('views/components/richeditor');
    File::ensureDirectoryExists($componentPath);

    // Create test components
    File::put(
        "{$componentPath}/alert.blade.php",
        <<<'BLADE'
@props([
    'message' => '',
    'type' => 'info'
])

<div class="alert alert-{{ $type }}">{{ $message }}</div>
BLADE
    );

    File::put(
        "{$componentPath}/button.blade.php",
        <<<'BLADE'
@props([
    'text' => '',
    'url' => '#'
])

<a href="{{ $url }}" {{ $attributes->merge(['class' => 'btn']) }}>{{ $text }}</a>
BLADE
    );
});

afterEach(function () {
    // Clean up
    $componentPath = resource_path('views/components/richeditor');
    if (File::exists($componentPath)) {
        File::deleteDirectory($componentPath);
    }
});

it('parses simple shortcodes correctly', function () {
    $content = 'Hello [alert message="Test alert" type="warning"] world';

    $result = ShortcodeParser::parse($content);

    expect($result)->toContain('alert-warning')
        ->and($result)->toContain('Test alert')
        ->and($result)->not->toContain('[alert');
});

it('handles multiple shortcodes in content', function () {
    $content = <<<'HTML'
<p>Welcome to our site!</p>
[alert message="First alert" type="info"]
<p>Some content here</p>
[alert message="Second alert" type="danger"]
HTML;

    $result = ShortcodeParser::parse($content);

    expect($result)->toContain('First alert')
        ->and($result)->toContain('Second alert')
        ->and($result)->toContain('alert-info')
        ->and($result)->toContain('alert-danger');
});

it('parses JSON attributes correctly', function () {
    // Create a component that accepts nested data
    File::put(
        resource_path('views/components/richeditor/card.blade.php'),
        <<<'BLADE'
@props([
    'data' => []
])

<div class="card">
    @foreach($data as $key => $value)
        <div>{{ $key }}: {{ $value }}</div>
    @endforeach
</div>
BLADE
    );

    $content = '[card data=\'{"title":"Test","author":"John"}\']';

    $result = ShortcodeParser::parse($content);

    expect($result)->toContain('title: Test')
        ->and($result)->toContain('author: John');
});

it('handles class attributes for merge support', function () {
    $content = '[button text="Click me" url="/test" class="btn-primary btn-lg"]';

    $result = ShortcodeParser::parse($content);

    expect($result)->toContain('btn-primary')
        ->and($result)->toContain('btn-lg')
        ->and($result)->toContain('Click me')
        ->and($result)->toContain('href="/test"');
});

it('returns original shortcode when component not found', function () {
    $content = 'Test [nonexistent-component prop="value"] content';

    $result = ShortcodeParser::parse($content);

    // Should return original shortcode unchanged
    expect($result)->toBe($content);
});

it('handles empty or null content gracefully', function () {
    expect(ShortcodeParser::parse(null))->toBe('')
        ->and(ShortcodeParser::parse(''))->toBe('')
        ->and(ShortcodeParser::parse('   '))->toBe('   ');
});

it('parses attributes with single and double quotes', function () {
    $content1 = '[alert message="Double quotes" type="info"]';
    $content2 = "[alert message='Single quotes' type='info']";

    $result1 = ShortcodeParser::parse($content1);
    $result2 = ShortcodeParser::parse($content2);

    expect($result1)->toContain('Double quotes')
        ->and($result2)->toContain('Single quotes');
});

it('handles components with dot notation', function () {
    // Create a nested component
    $sharedPath = resource_path('views/components/shared');
    File::ensureDirectoryExists($sharedPath);

    File::put(
        "{$sharedPath}/badge.blade.php",
        <<<'BLADE'
@props(['label' => ''])
<span class="badge">{{ $label }}</span>
BLADE
    );

    $content = '[shared.badge label="New"]';

    $result = ShortcodeParser::parse($content);

    expect($result)->toContain('badge')
        ->and($result)->toContain('New');

    // Cleanup
    File::deleteDirectory($sharedPath);
});
