<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Lang;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Game $game */
        $game = $this->resource;

        $labels = Lang::get("games.{$game->key}");

        return [
            'id' => $game->id,
            'title' => $labels['title'],
            'key' => $game->key,
            'description' => $labels['description'],
            'icon_url' => $game->icon_url,
            'is_active' => $game->is_active,
        ];
    }
}
