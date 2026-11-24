<?php

namespace App\Models;

use Database\Factories\EtablissementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property 'primaire'|'secondaire' $type
 */
class Etablissement extends Model
{
    /** @use HasFactory<EtablissementFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'nom',
        'type',
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

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'actif' => 'boolean',
        ];
    }
}
