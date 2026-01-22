<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class AllResource extends JsonResource
{
    public $status;
    public $message;
    public $resource;

    public function __construct($status, $message, $resource)
    {
        parent::__construct($resource);
        $this->status   = $status;
        $this->message  = $message;
        $this->resource = $resource;
    }

    public function toArray(Request $request): array
    {
        // 1) Deep normalize: ubah semua stdClass/Collection/Arrayable di semua level menjadi array
        $raw = $this->toArrayDeep($this->resource);

        // 2) Lowercase semua key
        $lowercased = $this->arrayKeysToLower($raw);

        // 3) Paksa 'id' => int (rekursif)
        $typed = $this->coerceIdToInt($lowercased);

        return [
            'success' => $this->status,
            'message' => $this->message,
            'data'    => $typed,
        ];
    }

    /**
     * Deep normalize: rekursif konversi ke array di semua level.
     */
    protected function toArrayDeep($data)
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        } elseif ($data instanceof Collection) {
            $data = $data->map(fn($v) => $this->toArrayDeep($v))->toArray();
        } elseif (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->toArrayDeep($v);
            }
        }

        return $data;
    }

    /**
     * Rekursif turunkan huruf key (handle array saja karena sudah deep-normalized).
     */
    protected function arrayKeysToLower($data)
    {
        if (!is_array($data)) return $data;

        $out = [];
        foreach ($data as $key => $value) {
            $newKey = is_string($key) ? strtolower($key) : $key;
            $out[$newKey] = $this->arrayKeysToLower($value);
        }
        return $out;
    }

    /**
     * Rekursif: jika menemukan key 'id' bernilai digit murni, cast ke int.
     * Tidak menyentuh key lain seperti id_device, dll.
     */
    protected function coerceIdToInt($data)
    {
        if (!is_array($data)) return $data;

        foreach ($data as $key => $value) {
            if ($key === 'id' && $this->isPureIntString($value)) {
                $data[$key] = (int) $value;
            } else {
                $data[$key] = $this->coerceIdToInt($value);
            }
        }
        return $data;
    }

    protected function isPureIntString($val): bool
    {
        if (is_int($val)) return true;
        if (!is_scalar($val)) return false;
        return preg_match('/^-?\d+$/', (string) $val) === 1;
    }
}
