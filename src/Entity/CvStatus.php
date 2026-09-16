<?php

namespace App\Entity;

enum CvStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
