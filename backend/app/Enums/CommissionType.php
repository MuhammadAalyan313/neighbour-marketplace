<?php

namespace App\Enums;

enum CommissionType: string
{
    case NONE = 'none';
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
}