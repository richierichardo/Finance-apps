<?php

namespace App\Enums;

/**
 * Union of income and expense category slugs for DB `category_transaction` / Category.slug.
 * Values must stay aligned with TransactionCategoryIncome and TransactionCategoryExpenses.
 */
enum TransactionCategory: string
{
    case Salary = 'salary';
    case Freelance = 'freelance';
    case Gift = 'gift';
    case Bonus = 'bonus';
    case Investment = 'investment';
    case Donation = 'donation';

    case Grocery = 'grocery';
    case Transport = 'transport';
    case Entertainment = 'entertainment';
    case Health = 'health';
    case Education = 'education';
    case Bills = 'bills';
    case Shopping = 'shopping';
    case Food = 'food';
    case Travel = 'travel';
    case Savings = 'savings';
    case Debt = 'debt';
    case Tax = 'tax';
    case Insurance = 'insurance';
    case Service = 'service';
    case Other = 'other';
}
