<?php

namespace App\Models;

use App\EtablissementType;
use Database\Factories\EtablissementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property EtablissementType $type
 * @property string|null $statut
 */
class Etablissement extends Model
{
    /** @use HasFactory<EtablissementFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'nom',
        'type',
        'statut',
        'arrondissement',
        'adresse',
        'latitude',
        'longitude',
        'telephone',
        'email',
        'actif',
    ];

    /**
     * Agents assigned to this establishment.
     *
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'etablissement_agent',
            'etablissement_id',
            'agent_id',
        )->withTimestamps();
    }

    /**
     * Annual reports recorded for this establishment.
     *
     * @return HasMany<AnnualReport, $this>
     */
    public function annualReports(): HasMany
    {
        return $this->hasMany(AnnualReport::class);
    }

    protected function casts(): array
    {
        return [
            'type' => EtablissementType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'actif' => 'boolean',
        ];
    }
}
