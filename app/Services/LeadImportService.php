<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\XLS\Reader as XlsReader;

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

        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'])) {
            $this->importSpreadsheet($file->getRealPath(), $extension, $user);
        } else {
            $csv = Reader::createFromPath($file->getRealPath(), 'r');
            $csv->setHeaderOffset(0);
            $records = $csv->getRecords();

            DB::transaction(function () use ($records, $user) {
                foreach ($records as $index => $record) {
                    $row = $index + 2; // account for header
                    $this->processRow($record, $row, $user);
                }
            });
        }

        return [
            'imported' => $this->imported,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }

    private function importSpreadsheet(string $path, string $extension, User $user): void
    {
        $reader = $extension === 'xlsx' ? new XlsxReader() : new XlsReader();
        $reader->open($path);

        DB::transaction(function () use ($reader, $user) {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = [];
                $rowIndex = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();

                    if ($rowIndex === 0) {
                        // First row is the header
                        $headers = array_map('strtolower', array_map('trim', $cells));
                        $rowIndex++;
                        continue;
                    }

                    $rowIndex++;
                    // Pad cells to match header count
                    $cells  = array_pad($cells, count($headers), null);
                    $record = array_combine($headers, $cells);

                    $this->processRow(
                        array_map(fn($v) => $v !== null ? (string) $v : '', $record),
                        $rowIndex,
                        $user
                    );
                }

                // Only process the first sheet
                break;
            }
        });

        $reader->close();
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

        $nullIfEmpty = fn(?string $v): ?string => ($v !== null && trim($v) !== '') ? trim($v) : null;

        $data = [
            'name'               => trim($record['name'] ?? $record['full_name'] ?? ''),
            'phone'              => $nullIfEmpty($record['phone'] ?? $record['mobile'] ?? null),
            'email'              => $nullIfEmpty($record['email'] ?? null),
            'source'             => strtolower(trim($record['source'] ?? 'csv_import')),
            'status'             => strtolower(trim($record['status'] ?? 'new')),
            'location'           => $nullIfEmpty($record['location'] ?? $record['area'] ?? null),
            'preferred_location' => $nullIfEmpty($record['preferred_location'] ?? $record['preferred_area'] ?? null),
            'property_type'      => $nullIfEmpty($record['property_type'] ?? null),
            'finishing_type'     => $nullIfEmpty($record['finishing_type'] ?? $record['finishing'] ?? null),
            'budget_min'         => $this->parseDecimal($record['budget_min'] ?? null),
            'budget_max'         => $this->parseDecimal($record['budget_max'] ?? null),
            'budget'             => $this->parseDecimal($record['budget'] ?? null),
            'intent'             => $nullIfEmpty(strtolower(trim($record['intent'] ?? $record['purpose'] ?? ''))),
            'inspection_at'      => $nullIfEmpty($record['inspection_at'] ?? $record['inspection_date'] ?? null),
            'notes'              => $nullIfEmpty($record['notes'] ?? null),
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
