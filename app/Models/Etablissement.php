<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etablissement extends Model
{
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

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'actif' => 'boolean',
        ];
    }
}
