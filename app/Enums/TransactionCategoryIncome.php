<?php

namespace App\Enums;

enum TransactionCategoryIncome: string
{
    /**
     * Category of transaction income
     */
    
    /** Category Income */
    case Salary = 'salary';
    case Freelance = 'freelance';
    case Gift = 'gift';
    case Bonus = 'bonus';
    case Investment = 'investment';
    case Donation = 'donation';
}