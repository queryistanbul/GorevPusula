<?php
/**
 * JSON Response Helper
 * 
 * Standardized response methods for consistent API output
 */

class Response
{
    /**
     * Send success response
     */
    public static function success($data = null, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    public static function error($message, $code = 400)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }

    /**
     * Send created response (201)
     */
    public static function created($data)
    {
        self::success($data, 201);
    }

    /**
     * Send unauthorized response (401)
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    /**
     * Send forbidden response (403)
     */
    public static function forbidden($message = 'Access denied')
    {
        self::error($message, 403);
    }

    /**
     * Send not found response (404)
     */
    public static function notFound($message = 'Resource not found')
    {
        self::error($message, 404);
    }

    /**
     * Send validation error response (400)
     */
    public static function validationError($errors)
    {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['errors' => $errors]);
        exit;
    }

    /**
     * Send server error response (500)
     */
    public static function serverError($message = 'Internal server error')
    {
        self::error($message, 500);
    }
}
