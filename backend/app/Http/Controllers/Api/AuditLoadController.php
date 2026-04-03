<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Load;
use App\Models\LogHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditLoadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // LOCAL TEST ONLY: includes "all"
            'window' => ['nullable', 'in:24,48,72,168,all'],
        ]);

        $window = (string) ($validated['window'] ?? 'all');

        $query = Load::query()
            ->with([
                'detail',
                'dispatcher:id,name,email',
                'jobJoin:id_join,job_name',
                'contact:id_contact,first_name,last_name,email,phone_number',
            ])
            ->where('is_deleted', 0)
            ->where('is_finished', 1)
            ->whereNull('audit_user_id')
            ->whereNotNull('entered_at');

        if ($window !== 'all') {
            $query->where('entered_at', '>=', now()->subHours((int) $window));
        }

        $loads = $query
            ->orderByDesc('entered_at')
            ->get()
            ->map(function (Load $load) use ($request) {
                $detail = $load->detail;

                return [
                    'id_load' => $load->id_load,
                    'journey' => $load->jobJoin?->job_name,
                    'load_date' => $load->load_date,
                    'delivery_time' => optional($load->delivery_time)?->format('Y-m-d H:i:s'),
                    'entered_at' => optional($load->entered_at)?->format('Y-m-d H:i:s'),
                    'load_number' => $detail?->load_number,
                    'ticket_number' => $detail?->ticket_number,
                    'net_lbs' => $detail?->net_lbs,
                    'driver_name' => $this->buildContactName($load->contact),
                    'dispatcher_name' => $load->dispatcher?->name,
                    'dispatcher_email' => $load->dispatcher?->email,
                    'can_audit' => (int) $load->id_user !== (int) $request->user()->id,
                    'is_own_load' => (int) $load->id_user === (int) $request->user()->id,
                ];
            })
            ->values();

        return response()->json([
            'window' => $window,
            'items' => $loads,
        ]);
    }

    public function show(Request $request, Load $load): JsonResponse
    {
        $load->loadMissing([
            'detail',
            'dispatcher:id,name,email',
            'jobJoin:id_join,job_name',
            'contact',
            'vehicle',
            'documents',
        ]);

        $driver = null;

        if ($load->contact) {
            $driver = Driver::query()
                ->where('id_contact', $load->contact->id_contact)
                ->first();
        }

        return response()->json([
            'item' => [
                'id_load' => $load->id_load,
                'can_audit' => (int) $load->id_user !== (int) $request->user()->id,
                'is_own_load' => (int) $load->id_user === (int) $request->user()->id,
                'journey' => $load->jobJoin?->job_name,
                'driver_name' => $this->buildContactName($load->contact),
                'dispatcher' => $load->dispatcher ? [
                    'id' => $load->dispatcher->id,
                    'name' => $load->dispatcher->name,
                    'email' => $load->dispatcher->email,
                ] : null,
                'contact' => $load->contact ? [
                    'id_contact' => $load->contact->id_contact,
                    'first_name' => $load->contact->first_name,
                    'last_name' => $load->contact->last_name,
                    'phone_number' => $load->contact->phone_number,
                    'email' => $load->contact->email,
                ] : null,
                'driver' => $driver ? [
                    'id_driver' => $driver->id_driver,
                    'id_vehicle' => $driver->id_vehicle,
                    'id_trailer' => $driver->id_trailer,
                    'status' => $driver->status,
                    'name' => $this->buildContactName($load->contact),
                ] : null,
                'vehicle' => $load->vehicle ? [
                    'id_vehicle' => $load->vehicle->id_vehicle,
                    'vehicle_name' => $load->vehicle->vehicle_name,
                    'vehicle_number' => $load->vehicle->vehicle_number,
                    'license_plate' => $load->vehicle->license_plate,
                    'vehicle_year' => $load->vehicle->vehicle_year,
                ] : null,
                'fields' => [
                    'load_date' => $load->load_date,
                    'delivery_time' => optional($load->delivery_time)?->format('Y-m-d H:i'),
                    'load_number' => $load->detail?->load_number,
                    'ticket_number' => $load->detail?->ticket_number,
                    'net_lbs' => $load->detail?->net_lbs,
                    'truck_number' => $load->detail?->truck_number,
                    'trailer_number' => $load->detail?->trailer_number,
                    'load_notes' => $load->detail?->load_notes,
                    'acc_desc' => $load->detail?->acc_desc,
                    'acc_amt' => $load->detail?->acc_amt,
                ],
                'bol' => $this->resolveBol($load),
                'audit_user_id' => $load->audit_user_id,
                'audited_at' => optional($load->audited_at)?->format('Y-m-d H:i:s'),
                'entered_at' => optional($load->entered_at)?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function update(Request $request, Load $load): JsonResponse
    {
        $this->guardAgainstSelfAudit($request, $load);

        $validated = $this->validateEditableFields($request);

        $result = DB::transaction(function () use ($request, $load, $validated) {
            $lockedLoad = Load::query()
                ->whereKey($load->id_load)
                ->lockForUpdate()
                ->with('detail')
                ->firstOrFail();

            if ($lockedLoad->audit_user_id) {
                abort(response()->json([
                    'message' => 'This load was already audited by another user.',
                ], 409));
            }

            $oldData = [];
            $newData = [];

            $this->syncLoadField($lockedLoad, 'load_date', $validated, $oldData, $newData);
            $this->syncLoadField($lockedLoad, 'delivery_time', $validated, $oldData, $newData);

            $detail = $lockedLoad->detail;
            if (! $detail) {
                abort(response()->json([
                    'message' => 'Load detail row not found.',
                ], 422));
            }

            foreach ([
                         'load_number',
                         'ticket_number',
                         'net_lbs',
                         'truck_number',
                         'trailer_number',
                         'load_notes',
                         'acc_desc',
                         'acc_amt',
                     ] as $field) {
                if (array_key_exists($field, $validated)) {
                    $old = $detail->{$field};
                    $new = $validated[$field];

                    if ((string) $old !== (string) $new) {
                        $oldData[$field] = $old;
                        $newData[$field] = $new;
                        $detail->{$field} = $new;
                    }
                }
            }

            if ($oldData !== []) {
                $lockedLoad->save();
                $detail->save();

                $this->writeLog(
                    userId: (int) $request->user()->id,
                    actionType: 'audit_update',
                    entityId: (int) $lockedLoad->id_load,
                    oldData: $oldData,
                    newData: $newData,
                    description: $request->user()->name . ' updated fields during audit.',
                    ipAddress: $request->ip()
                );
            }

            return $lockedLoad->fresh(['detail', 'jobJoin', 'dispatcher']);
        });

        return response()->json([
            'message' => 'Changes saved.',
            'item' => [
                'id_load' => $result->id_load,
                'journey' => $result->jobJoin?->job_name,
                'dispatcher_name' => $result->dispatcher?->name,
                'fields' => [
                    'load_date' => $result->load_date,
                    'delivery_time' => optional($result->delivery_time)?->format('Y-m-d H:i'),
                    'load_number' => $result->detail?->load_number,
                    'ticket_number' => $result->detail?->ticket_number,
                    'net_lbs' => $result->detail?->net_lbs,
                    'truck_number' => $result->detail?->truck_number,
                    'trailer_number' => $result->detail?->trailer_number,
                    'load_notes' => $result->detail?->load_notes,
                    'acc_desc' => $result->detail?->acc_desc,
                    'acc_amt' => $result->detail?->acc_amt,
                ],
            ],
        ]);
    }

    public function approve(Request $request, Load $load): JsonResponse
    {
        $this->guardAgainstSelfAudit($request, $load);

        DB::transaction(function () use ($request, $load) {
            $lockedLoad = Load::query()
                ->whereKey($load->id_load)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLoad->audit_user_id) {
                abort(response()->json([
                    'message' => 'This load was already audited by another user.',
                ], 409));
            }

            $lockedLoad->audit_user_id = $request->user()->id;
            $lockedLoad->audited_at = now();
            $lockedLoad->save();

            $this->writeLog(
                userId: (int) $request->user()->id,
                actionType: 'audit_approve',
                entityId: (int) $lockedLoad->id_load,
                oldData: null,
                newData: [
                    'audit_user_id' => $lockedLoad->audit_user_id,
                    'audited_at' => $lockedLoad->audited_at?->format('Y-m-d H:i:s'),
                ],
                description: $request->user()->name . ' audited and approved this load.',
                ipAddress: $request->ip()
            );
        });

        return response()->json([
            'message' => 'Load approved.',
        ]);
    }

    private function validateEditableFields(Request $request): array
    {
        return $request->validate([
            'load_date' => ['sometimes', 'nullable', 'string', 'max:10'],
            'delivery_time' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i'],
            'load_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ticket_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'net_lbs' => ['sometimes', 'nullable', 'integer'],
            'truck_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'trailer_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'load_notes' => ['sometimes', 'nullable', 'string', 'max:60'],
            'acc_desc' => ['sometimes', 'nullable', 'string', 'max:40'],
            'acc_amt' => ['sometimes', 'nullable', 'numeric'],
        ]);
    }

    private function guardAgainstSelfAudit(Request $request, Load $load): void
    {
        if ((int) $load->id_user === (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'load' => ['You cannot audit your own load.'],
            ]);
        }
    }

    private function syncLoadField(Load $load, string $field, array $validated, array &$oldData, array &$newData): void
    {
        if (! array_key_exists($field, $validated)) {
            return;
        }

        $old = $load->{$field};
        $new = $validated[$field];

        $oldCompare = $old instanceof \DateTimeInterface ? $old->format('Y-m-d H:i:s') : (string) $old;
        $newCompare = (string) $new;

        if ($oldCompare !== $newCompare) {
            $oldData[$field] = $oldCompare;
            $newData[$field] = $new;
            $load->{$field} = $new;
        }
    }

    private function buildContactName($contact): ?string
    {
        if (! $contact) {
            return null;
        }

        $parts = array_filter([
            $contact->first_name ?? null,
            $contact->last_name ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $name = trim(implode(' ', $parts));

        return $name !== '' ? $name : null;
    }

    private function writeLog(
        int $userId,
        string $actionType,
        int $entityId,
        ?array $oldData,
        ?array $newData,
        string $description,
        ?string $ipAddress
    ): void {
        LogHistory::query()->create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'entity_type' => 'Load',
            'entity_id' => $entityId,
            'old_data' => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data' => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'action_time' => now(),
            'ip_address' => $ipAddress,
            'description' => $description,
        ]);
    }

    private function resolveBol(Load $load): ?array
    {
        $detail = $load->detail;

        if ($detail && $detail->bol_path) {
            return [
                'path' => $detail->bol_path,
                'url' => $this->toPublicUrl($detail->bol_path),
                'type' => $this->normalizeFileType($detail->bol_type ?: $detail->bol_path),
                'source' => 'load_detail',
            ];
        }

        $document = $load->documents
            ->first(function ($doc) {
                return $doc->path;
            });

        if ($document) {
            return [
                'path' => $document->path,
                'url' => $this->toPublicUrl($document->path),
                'type' => $this->normalizeFileType($document->path),
                'source' => 'document',
            ];
        }

        return null;
    }

    private function toPublicUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    private function normalizeFileType(string $value): string
    {
        $value = strtolower($value);

        if (str_contains($value, 'pdf') || str_ends_with($value, '.pdf')) {
            return 'pdf';
        }

        return 'image';
    }
}
