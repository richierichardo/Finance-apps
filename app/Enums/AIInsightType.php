<?php

namespace App\Enums;

enum AIInsightType: string
{
    case MonthlySummary = 'monthly_summary';
    case BudgetAlert = 'budget_alert';
    case SpendingAlert = 'spending_alert';
    case Forecast = 'forecast';
}
