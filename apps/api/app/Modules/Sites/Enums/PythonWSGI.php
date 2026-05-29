<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum PythonWSGI: string
{
    case GUNICORN = 'gunicorn';
    case UVICORN = 'uvicorn';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::GUNICORN => 'Gunicorn',
            self::UVICORN => 'Uvicorn',
            self::NONE => 'None',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GUNICORN => 'info',
            self::UVICORN => 'success',
            self::NONE => 'neutral',
        };
    }
}
