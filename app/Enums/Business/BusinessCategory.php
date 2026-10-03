<?php

namespace App\Enums\Business;

enum BusinessCategory: string
{
    case Computers = 'computers';
    case CyberCafe = 'cyber_cafe';
    case HomeAppliances = 'home_appliances';
    case Clothing = 'clothing';
    case Grocery = 'grocery';
    case Pharmacy = 'pharmacy';
    case Restaurant = 'restaurant';
    case Beauty = 'beauty';
    case Auto = 'auto';
    case Construction = 'construction';
    case Education = 'education';
    case Healthcare = 'healthcare';
    case Freelance = 'freelance';
    case Other = 'other';

    public function label(): string
    {
        $key = 'business_categories.'.$this->value;
        $translated = __($key);

        return $translated === $key ? $this->name : $translated;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
