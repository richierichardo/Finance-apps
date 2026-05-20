<?php

use App\Services\AI\FinanceNLPNormalizerService;

beforeEach(function () {
    $this->normalizer = new FinanceNLPNormalizerService;
});

test('normalizes dri to dari and parses 20 ribu', function () {
    $result = $this->normalizer->normalize('Transfer 20 ribu dri gopay ke shopeepay');

    expect($result['normalized_text'])->toContain('dari')
        ->and($result['normalized_text'])->not->toContain('dri')
        ->and($this->normalizer->amountValues($result))->toContain(20000);
});

test('parses 1.5 juta and rp formatted amounts', function () {
    expect($this->normalizer->firstAmount('budget 1.5 juta'))->toBe(1500000)
        ->and($this->normalizer->firstAmount('rp 25.000'))->toBe(25000);
});

test('normalizes transfer typos', function () {
    $result = $this->normalizer->normalize('tf 50rb dari gopay');

    expect($result['normalized_text'])->toContain('transfer');
});
