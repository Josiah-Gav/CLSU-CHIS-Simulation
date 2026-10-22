<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Encounter extends Model
{
    protected $fillable = [
        'chis_encounter_id',
        'telemed_encounter_id',
        'patient_id',
        'encounter_type',
        'diagnosis',
        'completed_at',
        'payload',
        'payload_hash',
        'received_at',
    ];

    protected $casts = [
        'telemed_encounter_id' => 'integer',
        'completed_at' => 'datetime',
        'payload' => 'array',
        'received_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public static function formatChisId(int $id, int $year): string
    {
        return sprintf('ENC-%d-%06d', $year, $id);
    }

    /** The acknowledgement returned by POST /encounters. */
    public function toReceiptArray(): array
    {
        return [
            'chis_encounter_id' => $this->chis_encounter_id,
            'telemed_encounter_id' => $this->telemed_encounter_id,
            'patient_clsu_id' => $this->patient->clsu_id,
            'received_at' => $this->received_at->toIso8601String(),
            'status' => 'recorded',
        ];
    }

    /** One row of GET /encounters. */
    public function toSummaryArray(): array
    {
        return [
            'chis_encounter_id' => $this->chis_encounter_id,
            'telemed_encounter_id' => $this->telemed_encounter_id,
            'patient_clsu_id' => $this->patient->clsu_id,
            'encounter_type' => $this->encounter_type,
            'diagnosis' => $this->diagnosis,
            'completed_at' => $this->completed_at->toIso8601String(),
            'received_at' => $this->received_at->toIso8601String(),
        ];
    }

    /** GET /encounters/{id}: the summary plus the body exactly as Telemed sent it. */
    public function toDetailArray(): array
    {
        return $this->toSummaryArray() + ['payload' => $this->payload];
    }
}
