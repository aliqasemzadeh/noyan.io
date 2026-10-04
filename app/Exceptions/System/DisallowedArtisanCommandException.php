<?php

namespace App\Exceptions\System;

use InvalidArgumentException;

class DisallowedArtisanCommandException extends InvalidArgumentException
{
    public static function for(string $command): self
    {
        return new self(__('general.artisan_command_not_allowed', ['command' => $command]));
    }
}
