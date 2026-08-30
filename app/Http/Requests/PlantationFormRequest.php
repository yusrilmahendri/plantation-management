<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class PlantationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'slug' => 'slug',
            'location' => 'lokasi',
            'total_area' => 'luas total',
            'description' => 'deskripsi',
            'is_active' => 'status aktif',
            'plantation_public_id' => 'kebun',
            'code' => 'kode',
            'area' => 'luas',
            'crop_type' => 'jenis tanaman',
            'planting_year' => 'tahun tanam',
            'notes' => 'catatan',
            'phone' => 'telepon',
            'address' => 'alamat',
            'employment_type' => 'jenis pekerjaan',
            'daily_rate' => 'upah harian',
            'default_rate' => 'tarif default',
            'category' => 'kategori',
            'unit' => 'satuan',
            'minimum_stock' => 'stok minimum',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'numeric' => ':attribute harus berupa angka.',
            'min' => ':attribute tidak boleh kurang dari :min.',
            'max' => ':attribute terlalu panjang.',
            'unique' => ':attribute sudah digunakan.',
            'exists' => ':attribute tidak valid.',
            'in' => ':attribute tidak valid.',
            'boolean' => ':attribute tidak valid.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'between' => ':attribute harus antara :min dan :max.',
        ];
    }

    protected function booleanIsActive(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareIsActive(): void
    {
        if (! $this->has('is_active')) {
            if ($this->isMethod('post')) {
                $this->merge(['is_active' => true]);
            }

            return;
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
