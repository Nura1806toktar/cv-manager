<?php

namespace App\Entity;

enum RuleOperator: string
{
    case GT = 'gt';
    case GTE = 'gte';
    case LT = 'lt';
    case LTE = 'lte';
    case EQ = 'eq';
    case CHECKED = 'checked';
}
