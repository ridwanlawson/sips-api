<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AncakResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'fcba' => $this->fcba,
            'afdeling' => $this->afdeling,
            'fieldcode' => $this->fieldcode,
            'noancak' => $this->noancak,
            'luas' => $this->luas,
            'tph_id' => $this->tph_id,
            'tph' => $this->whenLoaded('tph'),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
