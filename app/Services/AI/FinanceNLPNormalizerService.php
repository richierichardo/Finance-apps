<?php

namespace App\Services\AI;

class FinanceNLPNormalizerService
{
    /**
     * @return array{
     *   original: string,
     *   normalized_text: string,
     *   tokens: list<string>,
     *   amount_candidates: list<array{raw: string, value: int, confidence: float, source: string, start: int, end: int}>,
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
     * @return list<array{raw: string, value: int, confidence: float, source: string, start: int, end: int}>
     */
    public function extractAmountCandidates(string $text): array
    {
        $amounts = [];
        $consumedSpans = [];

        $unitPattern = 'ribu|rb|k|rebu|ribuan|rban|juta|jt|jutaan|jtaan|milyar|miliar|m';

        if (preg_match_all(
            '/(?:rp\s*)?(\d+(?:[.,]\d+)?|\d{1,3}(?:[.,]\d{3})+)\s*('.$unitPattern.')\b/ui',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($matches as $m) {
                $raw = trim($m[0][0]);
                $start = $m[0][1];
                $end = $start + strlen($raw);
                $base = (float) str_replace(',', '.', $m[1][0]);
                $unit = mb_strtolower($m[2][0]);
                $multiplier = $this->unitMultiplier($unit);
                $value = (int) round($base * $multiplier);
                $amounts[] = [
                    'raw' => $raw,
                    'value' => $value,
                    'confidence' => 0.95,
                    'source' => 'multiplier',
                    'start' => $start,
                    'end' => $end,
                ];
                $consumedSpans[] = [$start, $end];
            }
        }

        if (preg_match_all(
            '/(?:rp\s*)?(\d{1,3}(?:\.\d{3})+)(?!\s*(?:'.$unitPattern.')\b)/ui',
            $text,
            $separatorMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($separatorMatches as $m) {
                $raw = trim($m[0][0]);
                $start = $m[0][1];
                $end = $start + strlen($raw);
                if ($this->spanOverlapsConsumed($start, $end, $consumedSpans)) {
                    continue;
                }
                $digits = (int) preg_replace('/\D/', '', $m[1][0]);
                if ($digits <= 0) {
                    continue;
                }
                $amounts[] = [
                    'raw' => $raw,
                    'value' => $digits,
                    'confidence' => 0.95,
                    'source' => 'separator',
                    'start' => $start,
                    'end' => $end,
                ];
                $consumedSpans[] = [$start, $end];
            }
        }

        if (preg_match_all(
            '/(?:rp\s*)?(\d+(?:,\d+)?)(?!\s*(?:'.$unitPattern.')\b)/ui',
            $text,
            $plainMatches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            foreach ($plainMatches as $m) {
                $raw = trim($m[0][0]);
                $start = $m[0][1];
                $end = $start + strlen($raw);
                if ($this->spanOverlapsConsumed($start, $end, $consumedSpans)) {
                    continue;
                }
                $numPart = $m[1][0];
                if (str_contains($numPart, '.')) {
                    continue;
                }
                $digits = (int) preg_replace('/\D/', '', $numPart);
                if ($digits <= 0) {
                    continue;
                }
                if ($this->isDuplicateValue($amounts, $digits)) {
                    continue;
                }
                $amounts[] = [
                    'raw' => $raw,
                    'value' => $digits,
                    'confidence' => 0.7,
                    'source' => 'plain',
                    'start' => $start,
                    'end' => $end,
                ];
                $consumedSpans[] = [$start, $end];
            }
        }

        usort($amounts, fn ($a, $b) => ($a['start'] ?? 0) <=> ($b['start'] ?? 0));

        return $amounts;
    }

    /**
     * @param  array{original: string, normalized_text: string, tokens: list<string>, amount_candidates: list<array{raw: string, value: int, confidence: float, source: string, start: int, end: int}>, hints: list<string>}  $normalized
     */
    public function primaryAmount(array $normalized): ?int
    {
        $best = $this->bestAmountCandidate($normalized);

        return $best['value'] ?? null;
    }

    /**
     * @param  array{original: string, normalized_text: string, tokens: list<string>, amount_candidates: list<array{raw: string, value: int, confidence: float, source: string, start: int, end: int}>, hints: list<string>}  $normalized
     * @return array{raw: string, value: int, confidence: float, source: string, start: int, end: int}|null
     */
    public function bestAmountCandidate(array $normalized): ?array
    {
        $candidates = $normalized['amount_candidates'] ?? [];
        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            $conf = ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
            if ($conf !== 0) {
                return $conf;
            }

            return ($a['start'] ?? 0) <=> ($b['start'] ?? 0);
        });

        return $candidates[0];
    }

    public function firstAmount(string $text): ?int
    {
        return $this->primaryAmount($this->normalize($text));
    }

    /**
     * @param  array{original: string, normalized_text: string, tokens: list<string>, amount_candidates: list<array{raw: string, value: int, confidence: float, source: string, start: int, end: int}>, hints: list<string>}  $normalized
     * @return list<int>
     */
    public function amountValues(array $normalized): array
    {
        return array_map(fn ($c) => $c['value'], $normalized['amount_candidates']);
    }

    /**
     * @param  list<array{raw: string, value: int, confidence: float, source: string, start: int, end: int}>  $amounts
     */
    private function isDuplicateValue(array $amounts, int $digits): bool
    {
        foreach ($amounts as $existing) {
            if ($existing['value'] === $digits) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{int, int}>  $consumedSpans
     */
    private function spanOverlapsConsumed(int $start, int $end, array $consumedSpans): bool
    {
        foreach ($consumedSpans as [$cStart, $cEnd]) {
            if ($start < $cEnd && $end > $cStart) {
                return true;
            }
        }

        return false;
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
