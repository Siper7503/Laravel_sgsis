<?php

namespace App\Models;

use App\AnnualReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualReportEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'annual_report_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'comment',
    ];

    /**
     * @return BelongsTo<AnnualReport, $this>
     */
    public function annualReport(): BelongsTo
    {
        return $this->belongsTo(AnnualReport::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => AnnualReportStatus::class,
            'to_status' => AnnualReportStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
