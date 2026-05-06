<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StuntingPredictionResource extends JsonResource
{
    /**
     * Transform the resource into an array for mobile consumption.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'prediction'      => $this->prediction,
            'risk_label'      => $this->risk_label,
            'probability'     => $this->probability,
            'explanation'     => $this->explanation,
            'recommendations' => $this->recommendations,
            'model_version'   => $this->model_version,
            'latency_ms'      => $this->latency_ms,
            'status'          => $this->status,
            'input_data'      => $this->input_data,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
