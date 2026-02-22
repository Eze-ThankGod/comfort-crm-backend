<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

class LeadImportService
{
    protected array $errors   = [];
    protected int   $imported = 0;
    protected int   $skipped  = 0;

    public function importCsv(UploadedFile $file, User $user): array
    {
        $this->errors   = [];
        $this->imported = 0;
        $this->skipped  = 0;

        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();

        DB::transaction(function () use ($records, $user) {
            foreach ($records as $index => $record) {
                $row = $index + 2; // account for header
                $this->processRow($record, $row, $user);
            }
        });

        return [
            'imported' => $this->imported,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }

    public function importArray(array $rows, User $user): array
    {
        $this->errors   = [];
        $this->imported = 0;
        $this->skipped  = 0;

        DB::transaction(function () use ($rows, $user) {
            foreach ($rows as $index => $record) {
                $this->processRow($record, $index + 1, $user);
            }
        });

        return [
            'imported' => $this->imported,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }

    private function processRow(array $record, int $row, User $user): void
    {
        // Normalize keys to lowercase
        $record = array_change_key_case($record, CASE_LOWER);

        $data = [
            'name'          => trim($record['name'] ?? $record['full_name'] ?? ''),
            'phone'         => trim($record['phone'] ?? $record['mobile'] ?? ''),
            'email'         => trim($record['email'] ?? ''),
            'source'        => strtolower(trim($record['source'] ?? 'csv_import')),
            'status'        => strtolower(trim($record['status'] ?? 'new')),
            'location'      => trim($record['location'] ?? $record['area'] ?? ''),
            'preferred_location' => trim($record['preferred_location'] ?? $record['preferred_area'] ?? ''),
            'property_type' => trim($record['property_type'] ?? ''),
            'finishing_type'=> trim($record['finishing_type'] ?? $record['finishing'] ?? ''),
            'budget_min'    => $this->parseDecimal($record['budget_min'] ?? $record['budget'] ?? null),
            'budget_max'    => $this->parseDecimal($record['budget_max'] ?? null),
            'budget'        => $this->parseDecimal($record['budget'] ?? null),
            'intent'        => strtolower(trim($record['intent'] ?? $record['purpose'] ?? '')),
            'inspection_at' => trim($record['inspection_at'] ?? $record['inspection_date'] ?? ''),
            'notes'         => trim($record['notes'] ?? ''),
        ];

        $validator = Validator::make($data, [
            'name'   => 'required|string|min:2',
            'phone'  => 'nullable|string',
            'email'  => 'nullable|email',
            'source' => 'in:website,csv_import,portal,referral,whatsapp,social_media,cold_call,other',
            'status' => 'in:new,contacted,interested,viewing,won,lost',
            'intent' => 'nullable|in:invest,move_in',
            'inspection_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            $this->errors[] = "Row {$row}: " . implode(', ', $validator->errors()->all());
            $this->skipped++;
            return;
        }

        // Skip duplicates by phone
        if ($data['phone'] && Lead::where('phone', $data['phone'])->exists()) {
            $this->errors[] = "Row {$row}: Lead with phone '{$data['phone']}' already exists – skipped.";
            $this->skipped++;
            return;
        }

        Lead::create(array_merge($data, [
            'created_by' => $user->id,
            'source'     => in_array($data['source'], ['website','csv_import','portal','referral','whatsapp','social_media','cold_call','other'])
                                ? $data['source'] : 'csv_import',
        ]));

        $this->imported++;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }
}
