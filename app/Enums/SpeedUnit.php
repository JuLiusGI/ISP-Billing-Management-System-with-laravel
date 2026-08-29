<?php

namespace App\Enums;

enum SpeedUnit: string
{
    case Kbps = 'Kbps';
    case Mbps = 'Mbps';
    case Gbps = 'Gbps';

    public function label(): string
    {
        return $this->value;
    }
}
