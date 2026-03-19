<?php

use Freis\FilamentCrudGenerator\Commands\FilamentCrud\CrudGenerator;

// --- splitFields() ---

it('splits simple comma-separated fields', function () {
    $result = CrudGenerator::splitFields('name:string,age:integer,active:boolean');

    expect($result)->toBe(['name:string', 'age:integer', 'active:boolean']);
});

it('handles between=1,12 without splitting on internal comma', function () {
    $result = CrudGenerator::splitFields('rating:integer:between=1,12:required,name:string');

    expect($result)->toBe(['rating:integer:between=1,12:required', 'name:string']);
});

it('handles exists=table,col without splitting on internal comma', function () {
    $result = CrudGenerator::splitFields('category_id:string:exists=categories,id,name:string');

    expect($result)->toBe(['category_id:string:exists=categories,id', 'name:string']);
});

it('handles multiple validation values with commas', function () {
    $result = CrudGenerator::splitFields('score:integer:between=1,100,email:string:required');

    expect($result)->toBe(['score:integer:between=1,100', 'email:string:required']);
});

it('returns empty array for empty string', function () {
    $result = CrudGenerator::splitFields('');

    expect($result)->toBe([]);
});

it('handles single field without commas', function () {
    $result = CrudGenerator::splitFields('name:string:required');

    expect($result)->toBe(['name:string:required']);
});

it('trims whitespace from fields', function () {
    $result = CrudGenerator::splitFields(' name:string , age:integer ');

    expect($result)->toBe(['name:string', 'age:integer']);
});
