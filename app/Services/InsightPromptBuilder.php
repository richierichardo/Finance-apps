<?php

namespace App\Services;

class InsightPromptBuilder
{
    /**
     * Build a compact prompt for monthly insight generation.
     *
     * @param  array<string, mixed>  $aggregatedData
     * @param  array<int, array<string, mixed>>  $ruleBasedInsights
     */
    public function buildMonthlySummaryPrompt(array $aggregatedData, array $ruleBasedInsights): string
    {
        $totalIncome = (float) ($aggregatedData['total_income'] ?? 0);
        $totalExpense = (float) ($aggregatedData['total_expense'] ?? 0);
        $netCashflow = (float) ($aggregatedData['net_cashflow'] ?? ($totalIncome - $totalExpense));

        $topCategories = $aggregatedData['top_expense_categories'] ?? [];
        $budgetUsage = $aggregatedData['budget_usage'] ?? [];

        $lines = [];

        $lines[] = "You are a personal finance assistant. Respond in the user's language (likely Indonesian). Be concise, clear, and actionable.";
        $lines[] = '';
        $lines[] = '## Data Summary (for internal reasoning, do not repeat verbatim)';
        $lines[] = sprintf('- Total income: %.2f', $totalIncome);
        $lines[] = sprintf('- Total expense: %.2f', $totalExpense);
        $lines[] = sprintf('- Net cashflow: %.2f', $netCashflow);

        if (! empty($topCategories)) {
            $lines[] = '';
            $lines[] = 'Top expense categories:';

            foreach (\array_slice($topCategories, 0, 5) as $cat) {
                $name = $cat['category_name'] ?? 'Unknown';
                $total = (float) ($cat['total'] ?? 0);
                $percent = (float) ($cat['percentage'] ?? 0);

                $lines[] = sprintf('- %s: %.2f (%.1f%% of expenses)', $name, $total, $percent);
            }
        }

        $importantBudgets = \array_filter($budgetUsage, static function ($b) {
            $status = $b['status'] ?? 'safe';

            return \in_array($status, ['warning', 'exceeded'], true);
        });

        if (! empty($importantBudgets)) {
            $lines[] = '';
            $lines[] = 'Budget status (only warning/exceeded):';

            foreach ($importantBudgets as $b) {
                $category = $b['category'] ?? 'Unknown';
                $percent = (float) ($b['percentage'] ?? 0);
                $status = $b['status'] ?? 'safe';

                $lines[] = sprintf('- %s: %s (%.1f%% of budget used)', $category, \strtoupper($status), $percent);
            }
        }

        if (! empty($ruleBasedInsights)) {
            $lines[] = '';
            $lines[] = 'Rule-based insights summary:';

            foreach ($ruleBasedInsights as $insight) {
                $title = $insight['title'] ?? ($insight['type'] ?? 'Insight');
                $desc = $insight['description'] ?? '';

                if (mb_strlen($desc) > 120) {
                    $desc = mb_substr($desc, 0, 117).'...';
                }

                $lines[] = sprintf('- %s: %s', $title, $desc);
            }
        }

        $lines[] = '';
        $lines[] = '## Instructions for the AI';
        $lines[] = 'Using the data summary and rule-based insights above, write:';
        $lines[] = '1. A concise monthly summary in 2–4 sentences.';
        $lines[] = '2. 2–5 actionable suggestions to improve the user\'s finances.';
        $lines[] = '3. A short, human-friendly explanation highlighting key points.';
        $lines[] = '';
        $lines[] = 'Respond using the following markdown structure:';
        $lines[] = '## Monthly Summary';
        $lines[] = '[2-4 sentences]';
        $lines[] = '';
        $lines[] = '## Actionable Suggestions';
        $lines[] = '- [Suggestion 1]';
        $lines[] = '- [Suggestion 2]';
        $lines[] = '...';
        $lines[] = '';
        $lines[] = '## Key Points';
        $lines[] = '[Brief explanation]';

        return implode("\n", $lines);
    }
}
