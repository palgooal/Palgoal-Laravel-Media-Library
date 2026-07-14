<?php

namespace Palgoal\MediaLibrary\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'file_name'          => $this->file_name,
            'file_original_name' => $this->file_original_name,
            'file_path'          => $this->file_path,
            'file_extension'     => $this->file_extension,
            'mime_type'          => $this->mime_type,
            'size'               => $this->size,
            'file_type'          => $this->file_type,
            'disk'               => $this->disk,
            'width'              => $this->width,
            'height'             => $this->height,
            'uploader_id'        => $this->uploader_id,

            'alt'         => $this->alt,
            'title'       => $this->title,
            'caption'     => $this->caption,
            'description' => $this->description,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // من الـ accessors في الموديل
            'url'           => $this->url,
            'readable_size' => $this->readable_size,
        ];
    }
}
