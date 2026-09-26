<?php

namespace App\Http\Controllers;

use App\Actions\Resource\UpdateCapacityAction;
use App\Http\Requests\UpdateCapacityRequest;
use Illuminate\Http\JsonResponse;

class ResourceController extends Controller
{
    public function updateCapacity(
        UpdateCapacityRequest $request,
        int $resource,
        UpdateCapacityAction $action,
    ): JsonResponse {
        $result = $action($resource, (int) $request->input('capacity'));

        return response()->json([
            'id' => $result->id,
            'name' => $result->name,
            'capacity' => $result->capacity,
        ]);

    }
}
