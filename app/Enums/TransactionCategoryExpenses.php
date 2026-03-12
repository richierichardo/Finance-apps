<?php

namespace App\Enums;

enum TransactionCategoryExpenses: string
{
    /**
     * Category of transaction expenses
     */
    
    /** Category Needs / Fixed Expenses */
    case Grocery = 'grocery';
    case Transport = 'transport';
    case Entertainment = 'entertainment';
    case Health = 'health';
    case Education = 'education';
    case Bills = 'bills';

    /** Category Shopping & Lifestyle */
    case Shopping = 'shopping';
    case Food = 'food';
    case Travel = 'travel';
    
    /** Category Financial & Future */
    case Savings = 'savings';
    case Investment = 'investment';
    case Debt = 'debt';

    /** Category Periodic Expenses (Tidak Rutin, Lain-Lain) */
    case Tax = 'tax';
    case Insurance = 'insurance';
    case Donation = 'donation';
    case Gift = 'gift';
    case Service = 'service';
}