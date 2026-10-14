<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    /**
     * Return a standardized success JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @param array<string, mixed> $extra
     * @return JsonResponse
     */
    public function successResponse(mixed $data = [], string $message = 'Opération effectuée avec succès.', int $code = 200, array $extra = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return response()->json($response, $code);
    }

    /**
     * Return a standardized created (201) JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    public function createdResponse(mixed $data = [], string $message = 'Ressource créée avec succès.'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return a standardized no content (204) JSON response.
     *
     * @return JsonResponse
     */
    public function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return a standardized paginated JSON response.
     *
     * @param LengthAwarePaginator $paginator
     * @param string|null $resourceClass
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function paginatedResponse(LengthAwarePaginator $paginator, ?string $resourceClass = null, string $message = 'Données récupérées avec succès.', int $code = 200): JsonResponse
    {
        $items = $paginator->items();

        if ($resourceClass && class_exists($resourceClass)) {
            $items = $resourceClass::collection(collect($items))->resolve();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $code);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param string $message
     * @param int $code
     * @param mixed $errors
     * @return JsonResponse
     */
    public function errorResponse(string $message = 'Une erreur est survenue.', int $code = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
