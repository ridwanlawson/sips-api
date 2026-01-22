<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAncakRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'fcba' => 'nullable|string|max:50',
            'afdeling' => 'nullable|string|max:50',
            'fieldcode' => 'nullable|string|max:50',
            'noancak' => 'required|string|max:50|unique:ancaks,noancak',
            'luas' => 'nullable|numeric|min:0',
            'tph_id' => 'nullable|integer|exists:tph,id',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'notes' => 'nullable|string',
            'created_by' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'noancak.required' => 'Nomor ancak harus diisi',
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
                'example' => 'AFD-01',
            ],
            'fieldcode' => [
                'description' => 'Kode lapangan atau blok tanaman',
                'example' => 'A01A',
            ],
            'noancak' => [
                'description' => 'Nomor ancak yang unik, tidak boleh duplikat dalam sistem',
                'example' => '1A',
                'required' => true,
            ],
            'luas' => [
                'description' => 'Luas ancak dalam hektar (numeric, 2 decimal places, min: 0)',
                'example' => '25.50',
            ],
            'tph_id' => [
                'description' => 'ID TPH (Tandan Per Hektar) yang terkait - mengacu ke tabel tph',
                'example' => '5',
            ],
            'status' => [
                'description' => 'Status ancak: active (aktif), inactive (tidak aktif), suspended (ditangguhkan)',
                'example' => 'active',
            ],
            'notes' => [
                'description' => 'Catatan atau keterangan tambahan tentang ancak',
                'example' => 'Ancak untuk panen reguler',
            ],
            'created_by' => [
                'description' => 'User ID atau nama yang membuat record ancak',
                'example' => 'admin',
            ],
        ];
    }
}
