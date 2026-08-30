<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkAttendanceRequest extends PlantationFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_status' => ['required', Rule::in(AttendanceStatus::values())],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'work_units' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $checkIn = $this->input('check_in');
            $checkOut = $this->input('check_out');

            if (filled($checkIn) && filled($checkOut) && strtotime((string) $checkOut) < strtotime((string) $checkIn)) {
                $validator->errors()->add('check_out', 'Waktu pulang tidak boleh sebelum waktu masuk.');
            }
        });
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'attendance_status' => 'status kehadiran',
            'check_in' => 'waktu masuk',
            'check_out' => 'waktu pulang',
            'work_units' => 'unit kerja',
        ]);
    }
}
