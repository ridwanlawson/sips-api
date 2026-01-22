<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAncakRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id') ?? $this->route('ancak');

        return [
            'fcba' => 'sometimes|string|max:50',
            'afdeling' => 'sometimes|string|max:50',
            'fieldcode' => 'sometimes|string|max:50',
            'noancak' => 'sometimes|string|max:50|unique:ancaks,noancak,' . $id,
            'luas' => 'sometimes|numeric|min:0',
            'tph_id' => 'sometimes|integer|exists:tph,id',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'notes' => 'sometimes|string',
            'updated_by' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'noancak.unique' => 'Nomor ancak sudah terdaftar',
            'tph_id.exists' => 'TPH yang dipilih tidak ditemukan',
            'luas.numeric' => 'Luas harus berupa angka',
            'luas.min' => 'Luas tidak boleh kurang dari 0',
            'status.in' => 'Status harus: active, inactive, atau suspended',
        ];
    }

    /**
     * Scribe Body Parameters Documentation
     */
    public function bodyParameters()
    {
        return [
            'fcba' => [
                'description' => 'Kode FCBA (Fruit Company/Estate Code) - identitas perusahaan/perkebunan',
                'example' => 'MTE',
            ],
            'afdeling' => [
                'description' => 'Kode afdeling atau divisi perkebunan',
                'example' => 'AFD-02',
            ],
            'fieldcode' => [
                'description' => 'Kode lapangan atau blok tanaman',
                'example' => 'A02B',
            ],
            'noancak' => [
                'description' => 'Nomor ancak yang unik (opsional untuk update)',
                'example' => '1B',
            ],
            'luas' => [
                'description' => 'Luas ancak dalam hektar (numeric, 2 decimal places, min: 0)',
                'example' => '28.75',
            ],
            'tph_id' => [
                'description' => 'ID TPH (Tandan Per Hektar) yang terkait - mengacu ke tabel tph',
                'example' => '6',
            ],
            'status' => [
                'description' => 'Status ancak: active (aktif), inactive (tidak aktif), suspended (ditangguhkan)',
                'example' => 'inactive',
            ],
            'notes' => [
                'description' => 'Catatan atau keterangan tambahan tentang ancak',
                'example' => 'Under maintenance',
            ],
            'updated_by' => [
                'description' => 'User ID atau nama yang melakukan update record ancak',
                'example' => 'supervisor',
            ],
        ];
    }
}
