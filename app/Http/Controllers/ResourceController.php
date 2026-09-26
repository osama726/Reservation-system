<?php

namespace App\Http\Controllers;

use App\Actions\Resource\UpdateCapacityAction;
use App\Http\Requests\UpdateCapacityRequest;
use App\Http\Resources\ResourceResource;

class ResourceController extends Controller
{
    public function updateCapacity(
        UpdateCapacityRequest $request,
        int $resource,
        UpdateCapacityAction $action,
    ): ResourceResource {
        $result = $action($resource, (int) $request->input('capacity'));

        return ResourceResource::make($result);
    }
}
