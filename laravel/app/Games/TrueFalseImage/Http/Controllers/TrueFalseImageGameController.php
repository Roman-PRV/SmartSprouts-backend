<?php

namespace App\Games\TrueFalseImage\Http\Controllers;

use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageResource;
use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageResultResource;
use App\Games\TrueFalseImage\Models\TrueFalseImage;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Games\TrueFalseImage\Http\Requests\CheckAnswersRequest;
use Illuminate\Http\JsonResponse;

class TrueFalseImageGameController extends Controller
{
    public function index(): JsonResponse
    {
        $levels = TrueFalseImage::with('statements:id,image_id,statement')->get();

        return response()->json(TrueFalseImageResource::collection($levels));
    }

    public function show(int $id): JsonResponse
    {
        $level = TrueFalseImage::with('statements:id,image_id,statement')->findOrFail($id);

        return response()->json(new TrueFalseImageResource($level));
    }

    public function checkAnswers(CheckAnswersRequest $request): JsonResponse
    {

        /** @var array<int, array{statement_id: int, answer: bool}> $answers */
        $answers = $request->validated()['answers'];

        $results = collect($answers)->map(function ($answer) {
            /** @var TrueFalseImageStatement $statement */
            $statement = TrueFalseImageStatement::findOrFail($answer['statement_id']);

            return [
                'statement_id' => $statement->id,
                'correct' => $statement->is_true === $answer['answer'],
                'is_true' => $statement->is_true,
                'explanation' => $statement->explanation,
            ];
        });

        return response()->json([
            'image_id' => $request->validated()['image_id'],
            'results' => TrueFalseImageResultResource::collection($results),
        ]);
    }
}
