<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\PlantationEntity;
use Illuminate\Validation\Rule;

class StoreWorkAttendanceRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PlantationEntity $entity */
        $entity = $this->route('plantationEntity');

        return [
            'worker_public_ids' => ['required', 'array', 'min:1'],
            'worker_public_ids.*' => [
                'required',
                'string',
                Rule::exists('workers', 'public_id')->where('plantation_entity_id', $entity->id),
            ],
            'attendance_status' => ['sometimes', Rule::in(AttendanceStatus::values())],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'worker_public_ids' => 'pekerja',
            'worker_public_ids.*' => 'pekerja',
            'attendance_status' => 'status kehadiran',
        ]);
    }
}
