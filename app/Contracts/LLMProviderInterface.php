<?php

namespace App\Contracts;

interface LLMProviderInterface
{
    /**
     * Generate text from a prompt using an LLM.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate(string $prompt, array $options = []): string;
}
