<?php

namespace App\Games\TrueFalseImage\Http\Controllers;

use App\Games\TrueFalseImage\Models\TrueFalseImage;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrueFalseImageGameController extends Controller
{
    public function index(): JsonResponse
    {
        $levels = TrueFalseImage::with('statements:id,image_id,statement')->get();

        return response()->json($levels);
    }

    public function show(int $id): JsonResponse
    {
        $level = TrueFalseImage::with('statements:id,image_id,statement')->findOrFail($id);

        return response()->json($level);
    }

    public function checkAnswers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_id' => 'required|exists:true_false_image_levels,id',
            'answers' => 'required|array',
            'answers.*.statement_id' => 'required|exists:true_false_image_statements,id',
            'answers.*.answer' => 'required|boolean',
        ]);

        /** @var array<int, array{statement_id: int, answer: bool}> $answers */
        $answers = $validated['answers'];

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
            'image_id' => $validated['image_id'],
            'results' => $results,
        ]);
    }
}
