<?php

namespace App\Services\AI;

class FinanceNLPNormalizerService
{
    /**
     * @return array{
     *   original: string,
     *   normalized_text: string,
     *   tokens: list<string>,
     *   amount_candidates: list<array{raw: string, value: int, start: int|null, end: int|null}>,
     *   hints: list<string>
     * }
     */
    public function normalize(string $input): array
    {
        $original = trim($input);
        $text = mb_strtolower($original);
        $hints = [];

        $typoMap = [
            '/\bdrih\b/u' => 'dari',
            '/\bdri\b/u' => 'dari',
            '/\bdr\b/u' => 'dari',
            '/\btrsfer\b/u' => 'transfer',
            '/\btf\b/u' => 'transfer',
            '/\btrf\b/u' => 'transfer',
            '/\bpindahin\b/u' => 'transfer',
            '/\bpindahkan\b/u' => 'transfer',
            '/\bpindah\b/u' => 'transfer',
            '/\bduit\b/u' => 'uang',
            '/\bmake\b/u' => 'pakai',
            '/\bcatet\b/u' => 'catat',
            '/\bcatatin\b/u' => 'catat',
            '/\brebu\b/u' => 'ribu',
            '/\brban\b/u' => 'ribuan',
            '/\bjtaan\b/u' => 'jutaan',
        ];

        foreach ($typoMap as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        $amountCandidates = $this->extractAmountCandidates($text);
        if (! empty($amountCandidates)) {
            $hints[] = 'amounts_detected';
        }

        $tokens = preg_split('/\s+/u', trim($text)) ?: [];

        return [
            'original' => $original,
            'normalized_text' => $text,
            'tokens' => array_values(array_filter($tokens)),
            'amount_candidates' => $amountCandidates,
            'hints' => $hints,
        ];
    }

    /**
     * @return list<array{raw: string, value: int, start: int|null, end: int|null}>
     */
    public function extractAmountCandidates(string $text): array
    {
        $amounts = [];

        $unitPattern = 'ribu|rb|k|rebu|ribuan|rban|juta|jt|jutaan|jtaan|milyar|miliar|m';

        if (preg_match_all(
            '/(?:rp\s*)?(\d+(?:[.,]\d+)?|\d{1,3}(?:[.,]\d{3})+)\s*('.$unitPattern.')\b/ui',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($matches as $m) {
                $raw = trim($m[0][0]);
                $base = (float) str_replace(',', '.', $m[1][0]);
                $unit = mb_strtolower($m[2][0]);
                $multiplier = $this->unitMultiplier($unit);
                $value = (int) round($base * $multiplier);
                $amounts[] = [
                    'raw' => $raw,
                    'value' => $value,
                    'start' => $m[0][1],
                    'end' => $m[0][1] + strlen($raw),
                ];
            }
        }

        if (preg_match_all(
            '/(?:rp\s*)?(\d{1,3}(?:[.,]\d{3})+|\d+)(?!\s*(?:'.$unitPattern.')\b)/ui',
            $text,
            $plainMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($plainMatches as $m) {
                $raw = trim($m[0][0]);
                $digits = (int) preg_replace('/\D/', '', $m[1][0]);
                if ($digits <= 0) {
                    continue;
                }
                $duplicate = false;
                foreach ($amounts as $existing) {
                    if ($existing['value'] === $digits) {
                        $duplicate = true;
                        break;
                    }
                }
                if (! $duplicate) {
                    $amounts[] = [
                        'raw' => $raw,
                        'value' => $digits,
                        'start' => $m[0][1],
                        'end' => $m[0][1] + strlen($raw),
                    ];
                }
            }
        }

        usort($amounts, fn ($a, $b) => ($a['start'] ?? 0) <=> ($b['start'] ?? 0));

        return $amounts;
    }

    /**
     * @param  array{original: string, normalized_text: string, tokens: list<string>, amount_candidates: list<array{raw: string, value: int, start: int|null, end: int|null}>, hints: list<string>}  $normalized
     */
    public function primaryAmount(array $normalized): ?int
    {
        return $normalized['amount_candidates'][0]['value'] ?? null;
    }

    public function firstAmount(string $text): ?int
    {
        return $this->primaryAmount($this->normalize($text));
    }

    /**
     * @param  array{original: string, normalized_text: string, tokens: list<string>, amount_candidates: list<array{raw: string, value: int, start: int|null, end: int|null}>, hints: list<string>}  $normalized
     */
    public function amountValues(array $normalized): array
    {
        return array_map(fn ($c) => $c['value'], $normalized['amount_candidates']);
    }

    private function unitMultiplier(string $unit): int
    {
        return match (true) {
            in_array($unit, ['ribu', 'rb', 'k', 'rebu', 'ribuan', 'rban'], true) => 1000,
            in_array($unit, ['juta', 'jt', 'jutaan', 'jtaan'], true) => 1000000,
            in_array($unit, ['milyar', 'miliar', 'm'], true) => 1000000000,
            default => 1,
        };
    }
}
