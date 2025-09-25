<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Game $game */
        $game = $this->resource;

        return [
            'id' => $game->id,
            'title' => $game->title,
            'description' => $game->description,
            'icon_url' => $game->icon_url,
            'is_active' => $game->is_active,
        ];
    }
}
