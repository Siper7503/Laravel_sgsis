<?php

namespace App\Models;

use App\AnnualReportStatus;
use App\AnnualReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property AnnualReportType $report_type
 * @property AnnualReportStatus $status
 * @property array<string, mixed> $data
 */
class AnnualReport extends Model
{
    protected $fillable = [
        'etablissement_id',
        'created_by',
        'school_year',
        'report_type',
        'form_version',
        'status',
        'data',
    ];

    /**
     * @return BelongsTo<Etablissement, $this>
     */
    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnualReportEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AnnualReportEvent::class)->orderBy('created_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'report_type' => AnnualReportType::class,
            'status' => AnnualReportStatus::class,
            'form_version' => 'integer',
            'data' => 'array',
        ];
    }
}
