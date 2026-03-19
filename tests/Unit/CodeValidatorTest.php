<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CodeValidator;

beforeEach(function () {
    $this->validator = new CodeValidator;
});

// --- validateSyntax() ---

it('returns true for balanced braces', function () {
    $code = '{ { } }';
    expect($this->validator->validateSyntax($code))->toBeTrue();
});

it('returns true for balanced parentheses', function () {
    $code = '( ( ) )';
    expect($this->validator->validateSyntax($code))->toBeTrue();
});

it('returns true for balanced brackets', function () {
    $code = '[ [ ] ]';
    expect($this->validator->validateSyntax($code))->toBeTrue();
});

it('returns true for mixed balanced symbols', function () {
    $code = 'function test() { $arr = [1, 2, (3 + 4)]; }';
    expect($this->validator->validateSyntax($code))->toBeTrue();
});

it('returns true for empty string', function () {
    expect($this->validator->validateSyntax(''))->toBeTrue();
});

it('returns true for PHP class code', function () {
    $code = <<<'PHP'
class Test {
    public function method(): array {
        return ['key' => fn($x) => ($x + 1)];
    }
}
PHP;
    expect($this->validator->validateSyntax($code))->toBeTrue();
});

it('returns false for unbalanced opening brace', function () {
    $code = '{ {';
    expect($this->validator->validateSyntax($code))->toBeFalse();
});

it('returns false for unbalanced closing brace', function () {
    $code = '} }';
    expect($this->validator->validateSyntax($code))->toBeFalse();
});

it('returns false for unbalanced parentheses', function () {
    $code = '( ( )';
    expect($this->validator->validateSyntax($code))->toBeFalse();
});

it('returns false for unbalanced brackets', function () {
    $code = '[ ]]]';
    expect($this->validator->validateSyntax($code))->toBeFalse();
});

it('returns false for mismatched symbols', function () {
    $code = '( { ) }';
    expect($this->validator->validateSyntax($code))->toBeFalse();
});

it('returns false for closing without opening', function () {
    $code = '}';
    expect($this->validator->validateSyntax($code))->toBeFalse();
});

// --- findMatchingCloseBrace() ---

it('finds correct position of matching close brace', function () {
    $content = '{ content }';
    $result = $this->validator->findMatchingCloseBrace($content, 0);
    expect($result)->toBe(10);
});

it('finds correct position with nested braces', function () {
    $content = '{ { inner } outer }';
    $result = $this->validator->findMatchingCloseBrace($content, 0);
    expect($result)->toBe(18);
});

it('finds correct position of inner brace', function () {
    $content = '{ { inner } outer }';
    $result = $this->validator->findMatchingCloseBrace($content, 2);
    expect($result)->toBe(10);
});

it('handles deeply nested braces', function () {
    $content = '{ { { deep } } }';
    $result = $this->validator->findMatchingCloseBrace($content, 0);
    expect($result)->toBe(15);
});

it('returns false when no matching close brace found', function () {
    $content = '{ no close';
    $result = $this->validator->findMatchingCloseBrace($content, 0);
    expect($result)->toBeFalse();
});

it('returns false for content with only opening braces', function () {
    $content = '{ { {';
    $result = $this->validator->findMatchingCloseBrace($content, 0);
    expect($result)->toBeFalse();
});

it('finds matching brace in PHP method', function () {
    $content = <<<'PHP'
public function test(): void {
    if (true) {
        echo 'hello';
    }
}
PHP;
    $openPos = strpos($content, '{');
    $result = $this->validator->findMatchingCloseBrace($content, $openPos);

    // The last } in the content
    expect($result)->toBe(strrpos($content, '}'));
});
