<?php

namespace App\Models;

use Database\Factories\PlantationAccessTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantationAccessToken extends Model
{
    /** @use HasFactory<PlantationAccessTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'plantation_entity_id',
        'label',
        'token_hash',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function plantationEntity(): BelongsTo
    {
        return $this->belongsTo(PlantationEntity::class);
    }

    public static function generatePlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lte(now())) {
            return false;
        }

        $entity = $this->plantationEntity;

        if ($entity === null || ! $entity->is_active) {
            return false;
        }

        return true;
    }
}
