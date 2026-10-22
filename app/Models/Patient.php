<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    public const PROFILE_LISTS = [
        'allergies',
        'chronic_conditions',
        'current_medications',
        'immunizations',
        'family_history',
    ];

    protected $fillable = [
        'clsu_id',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'birth_date',
        'patient_type',
        'college',
        'blood_type',
        'medical_profile',
        'record_updated_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'medical_profile' => 'array',
        'record_updated_at' => 'datetime',
    ];

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    /**
     * Exact string match after trimming. Never cast to a number: 23-0001 and
     * 23-1 are different people.
     */
    public static function findByClsuId(string $clsuId): ?self
    {
        return static::query()->where('clsu_id', trim($clsuId))->first();
    }

    /**
     * The contract's patient shape. Every profile list is always an array,
     * never null, even when the stored document omits it.
     */
    public function toApiArray(): array
    {
        $profile = $this->medical_profile ?? [];

        return [
            'clsu_id' => $this->clsu_id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'sex' => $this->sex,
            'birth_date' => $this->birth_date?->toDateString(),
            'patient_type' => $this->patient_type,
            'college' => $this->college,
            'blood_type' => $this->blood_type,
            'medical_profile' => collect(self::PROFILE_LISTS)
                ->mapWithKeys(fn (string $key) => [$key => array_values($profile[$key] ?? [])])
                ->all(),
            'record_updated_at' => $this->record_updated_at?->toIso8601String(),
        ];
    }
}
