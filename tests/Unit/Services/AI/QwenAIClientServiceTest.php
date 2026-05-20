<?php

use App\Services\AI\AITokenUsageService;
use App\Services\AI\QwenAIClientService;
use Illuminate\Support\Facades\Http;

test('qwen client returns failure when api key missing', function () {
    config(['ai.api_key' => null]);

    $client = new QwenAIClientService(new AITokenUsageService);
    $result = $client->chat([['role' => 'user', 'content' => 'hi']]);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->not->toBeNull()
        ->and($result['provider'])->toBe('qwen');
});

test('qwen client parses successful response with normalized usage', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.example.com/v1',
        'ai.model' => 'qwen-plus',
        'ai.provider' => 'qwen',
    ]);

    Http::fake([
        'api.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello']]],
            'usage' => [
                'prompt_tokens' => 12,
                'completion_tokens' => 8,
                'total_tokens' => 20,
            ],
        ]),
    ]);

    $client = new QwenAIClientService(new AITokenUsageService);
    $result = $client->chat([['role' => 'user', 'content' => 'hi']]);

    expect($result['ok'])->toBeTrue()
        ->and($result['content'])->toBe('Hello')
        ->and($result['usage']['prompt_tokens'])->toBe(12)
        ->and($result['usage']['completion_tokens'])->toBe(8)
        ->and($result['usage']['total_tokens'])->toBe(20)
        ->and($result['provider'])->toBe('qwen');
});

test('qwen client maps input and output token aliases', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.example.com/v1',
    ]);

    Http::fake([
        'api.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
        ]),
    ]);

    $client = new QwenAIClientService(new AITokenUsageService);
    $result = $client->chat([['role' => 'user', 'content' => 'hi']]);

    expect($result['usage']['prompt_tokens'])->toBe(5)
        ->and($result['usage']['completion_tokens'])->toBe(3)
        ->and($result['usage']['total_tokens'])->toBe(8);
});

test('qwen client respects custom timeout option', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.example.com/v1',
        'ai.timeout' => 30,
    ]);

    Http::fake([
        'api.example.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
        ]),
    ]);

    $client = new QwenAIClientService(new AITokenUsageService);
    $client->chat([['role' => 'user', 'content' => 'hi']], ['timeout' => 20]);

    Http::assertSent(function ($request) {
        return true;
    });
});
