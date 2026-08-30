<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
