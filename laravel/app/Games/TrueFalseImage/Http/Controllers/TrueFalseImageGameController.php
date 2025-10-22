<?php

namespace App\Games\TrueFalseImage\Http\Controllers;

use App\Games\TrueFalseImage\Http\Requests\CheckAnswersRequest;
use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageResource;
use App\Games\TrueFalseImage\Http\Resources\TrueFalseImageResultResource;
use App\Games\TrueFalseImage\Models\TrueFalseImage;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TrueFalseImageGameController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/true-false-images",
     *     summary="List all True/False Image levels",
     *     description="Returns a list of available game levels, each with an image and associated statements.",
     *     tags={"TrueFalseImage"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of levels retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/TrueFalseImage.Level")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $levels = TrueFalseImage::with('statements:id,image_id,statement')->get();

        return response()->json(TrueFalseImageResource::collection($levels));
    }

    /**
     * @OA\Get(
     *     path="/api/true-false-images/{id}",
     *     summary="Retrieve a specific True/False Image level",
     *     description="Returns a single level with its associated image and statements.",
     *     tags={"TrueFalseImage"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the level to retrieve",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Level data retrieved successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/TrueFalseImage.Level")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Level not found"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $level = TrueFalseImage::with('statements:id,image_id,statement')->findOrFail($id);

        return response()->json(new TrueFalseImageResource($level));
    }

    /**
     * @OA\Post(
     *     path="/api/true-false-images/check",
     *     summary="Validate player's answers for a True/False Image level",
     *     description="Checks the submitted answers against the correct values and returns the result per statement.",
     *     tags={"TrueFalseImage"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Player's submitted answers for a specific image level",
     *
     *         @OA\JsonContent(
     *             required={"image_id", "answers"},
     *
     *             @OA\Property(property="image_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="answers",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"statement_id", "answer"},
     *
     *                     @OA\Property(property="statement_id", type="integer", example=10),
     *                     @OA\Property(property="answer", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Answer validation results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="image_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="results",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/TrueFalseImage.Result")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
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
