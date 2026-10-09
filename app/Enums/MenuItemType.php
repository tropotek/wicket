<?php

namespace App\Enums;

enum MenuItemType: string
{
    case Item = 'item';           // link to a page
    case Dropdown = 'dropdown';   // top-level holder of items and dividers
    case Divider = 'divider';     // separator inside a dropdown
}
