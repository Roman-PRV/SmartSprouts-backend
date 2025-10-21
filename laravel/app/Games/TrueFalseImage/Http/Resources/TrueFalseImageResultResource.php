<?php

namespace App\Games\TrueFalseImage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrueFalseImageResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'statement_id' => $this['statement_id'],
            'correct' => $this['correct'],
            'is_true' => $this['is_true'],
            'explanation' => $this['explanation'],
        ];
    }
}
