<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'url' => $this->url(),
            'thumb' => $this->thumbUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->aspectRatio(),
            'status' => $this->status,
            'duration' => $this->duration_seconds,
            'is_video' => $this->isVideo(),
        ];
    }
}
