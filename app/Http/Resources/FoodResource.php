<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'category'      => $this->category,
            'protein'       => $this->protein,
            'calories'      => $this->calories,
            'fat'           => $this->fat,
            'carbohydrates' => $this->carbohydrates,
            'iron'          => $this->iron,
            'calcium'       => $this->calcium,
            'vitamin_a'     => $this->vitamin_a,
            'image_url'     => $this->image_url,
            'description'   => $this->description,
        ];
    }
}
