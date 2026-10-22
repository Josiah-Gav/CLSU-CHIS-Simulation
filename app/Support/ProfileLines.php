<?php

namespace App\Support;

use App\Models\Patient;

/**
 * Converts the medical-profile lists to and from the viewer form's text
 * areas: one item per line, fields separated by "|". No JavaScript needed,
 * and the format is shown next to each box.
 */
class ProfileLines
{
    /**
     * Per list: the fields in order. A field with a list of values must be one
     * of them; "required" fields may not be blank; the rest become null when blank.
     */
    public const FIELDS = [
        'allergies' => [
            'substance' => 'required',
            'reaction' => 'optional',
            'severity' => ['mild', 'moderate', 'severe'],
        ],
        'chronic_conditions' => [
            'name' => 'required',
            'diagnosed_on' => 'date',
            'status' => ['active', 'controlled', 'resolved'],
        ],
        'current_medications' => [
            'name' => 'required',
            'dose' => 'optional',
            'frequency' => 'optional',
        ],
        'immunizations' => [
            'vaccine' => 'required',
            'dose' => 'optional',
            'date_administered' => 'date',
        ],
        'family_history' => [
            'relation' => 'required',
            'condition' => 'required',
        ],
    ];

    /** An example line, shown as the empty text area's placeholder. */
    public const EXAMPLES = [
        'allergies' => 'Penicillin | Hives | severe',
        'chronic_conditions' => 'Asthma | 2015-06-01 | controlled',
        'current_medications' => 'Salbutamol inhaler | 100 mcg | as needed',
        'immunizations' => 'Hepatitis B | 3rd dose | 2021-08-10',
        'family_history' => 'father | Hypertension',
    ];

    /** Human-readable format line shown under each text area. */
    public static function hint(string $list): string
    {
        return collect(self::FIELDS[$list])
            ->map(fn ($rule, $field) => is_array($rule) ? $field.' ('.implode('/', $rule).')' : $field.($rule === 'date' ? ' (YYYY-MM-DD, optional)' : ($rule === 'optional' ? ' (optional)' : '')))
            ->implode(' | ');
    }

    public static function toText(Patient $patient, string $list): string
    {
        return collect($patient->medical_profile[$list] ?? [])
            ->map(fn (array $item) => collect(array_keys(self::FIELDS[$list]))->map(fn ($f) => (string) ($item[$f] ?? ''))->implode(' | '))
            ->implode("\n");
    }

    /**
     * @return array{0: array, 1: array<int, string>} [items, errors]
     */
    public static function parse(string $list, ?string $text): array
    {
        $items = [];
        $errors = [];
        $fields = self::FIELDS[$list];

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $lineNo = $index + 1;

            if (count($parts) > count($fields)) {
                $errors[] = "Line {$lineNo}: expected at most ".count($fields).' fields separated by "|".';

                continue;
            }

            $item = [];
            $parts = array_pad($parts, count($fields), '');

            foreach (array_keys($fields) as $i => $field) {
                $rule = $fields[$field];
                $value = $parts[$i];

                if (is_array($rule)) {
                    $value = strtolower($value);
                    if (! in_array($value, $rule, true)) {
                        $errors[] = "Line {$lineNo}: {$field} must be one of ".implode(', ', $rule).'.';
                    }
                } elseif ($rule === 'required' && $value === '') {
                    $errors[] = "Line {$lineNo}: {$field} is required.";
                } elseif ($rule === 'date' && $value !== '' && ! self::isDate($value)) {
                    $errors[] = "Line {$lineNo}: {$field} must be a date like 2024-01-31.";
                } elseif (mb_strlen($value) > 150) {
                    $errors[] = "Line {$lineNo}: {$field} is longer than 150 characters.";
                }

                $item[$field] = $value === '' ? null : $value;
            }

            $items[] = $item;
        }

        return [$items, $errors];
    }

    private static function isDate(string $value): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
