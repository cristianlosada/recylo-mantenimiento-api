<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Respuesta exitosa estándar
     */
    public static function success(
        $data = null, 
        string $message = 'Operación exitosa', 
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'status_code' => $code,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Respuesta de error estándar
     */
    public static function error(
        string $message = 'Error en la operación',
        int $code = 400,
        $errors = null,
        $data = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'status_code' => $code,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Respuesta de validación
     */
    public static function validation(
        array $errors,
        string $message = 'Errores de validación'
    ): JsonResponse {
        return self::error($message, 422, $errors);
    }

    /**
     * Respuesta no autorizado
     */
    public static function unauthorized(
        string $message = 'No autorizado'
    ): JsonResponse {
        return self::error($message, 401);
    }

    /**
     * Respuesta no encontrado
     */
    public static function notFound(
        string $message = 'Recurso no encontrado'
    ): JsonResponse {
        return self::error($message, 404);
    }

    /**
     * Respuesta prohibido
     */
    public static function forbidden(
        string $message = 'Acceso prohibido'
    ): JsonResponse {
        return self::error($message, 403);
    }

    /**
     * Respuesta de paginación
     */
    public static function paginated(
        $data,
        array $pagination,
        string $message = 'Datos recuperados exitosamente'
    ): JsonResponse {
        return self::success($data, $message, 200, [
            'pagination' => $pagination
        ]);
    }

    /**
     * Respuesta de creación
     */
    public static function created(
        $data = null,
        string $message = 'Recurso creado exitosamente'
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    /**
     * Respuesta de actualización
     */
    public static function updated(
        $data = null,
        string $message = 'Recurso actualizado exitosamente'
    ): JsonResponse {
        return self::success($data, $message, 200);
    }

    /**
     * Respuesta de eliminación
     */
    public static function deleted(
        string $message = 'Recurso eliminado exitosamente'
    ): JsonResponse {
        return self::success(null, $message, 200);
    }

    /**
     * Respuesta sin contenido
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}