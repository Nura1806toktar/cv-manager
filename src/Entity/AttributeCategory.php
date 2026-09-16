<?php

namespace App\Entity;

enum AttributeCategory: string
{
    case CERTIFICATION = 'certification';
    case DOMAIN_KNOWLEDGE = 'domain_knowledge';
    case PERSONAL_INFORMATION = 'personal_information';
    case SOFT_SKILLS = 'soft_skills';
    case LANGUAGE = 'language';
    case TECHNICAL_SKILL = 'technical_skill';
}
