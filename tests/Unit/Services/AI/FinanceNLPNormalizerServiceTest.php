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

test('50 ribu does not produce spurious low amount candidates', function () {
    $result = $this->normalizer->normalize('buat wallet cash nama uang dompet saldo 50 ribu');

    expect($this->normalizer->amountValues($result))->toBe([50000])
        ->and($this->normalizer->primaryAmount($result))->toBe(50000);
});

test('indonesian thousand separator 348.455', function () {
    expect($this->normalizer->firstAmount('saldo 348.455'))->toBe(348455)
        ->and($this->normalizer->firstAmount('saldo 10.861'))->toBe(10861);
});

test('amount candidates include confidence and source', function () {
    $result = $this->normalizer->normalize('50 ribu');

    expect($result['amount_candidates'][0])->toHaveKeys(['raw', 'value', 'confidence', 'source'])
        ->and($result['amount_candidates'][0]['source'])->toBe('multiplier')
        ->and($result['amount_candidates'][0]['value'])->toBe(50000);
});
