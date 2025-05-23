<?php

namespace App\Http\Controllers;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controller as BaseController;




abstract class Controller extends BaseController
{


    /**
     * Send a response with data and an optional message.
     *
     * @param mixed $data The data to be included in the response.
     * @param bool $message Optional. A message to be included in the response.
     *
     * @return \Illuminate\Http\JsonResponse A JSON response containing the success status, data, and an optional message.
     */
    public function sendResponse($data, $message = false, $code = Response::HTTP_OK ): JsonResponse
    {
    	$response = [
            'success' => true,
            'data'    => $data ?? [],
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    /**
     * Send an error response with an optional error message and code.
     *
     * @param string $error The error message to be included in the response.
     * @param array $errorMessages Optional. An array of error messages to be included in the response.
     * @param int $code Optional. The HTTP status code for the error response. Defaults to Response::HTTP_NOT_FOUND.
     *
     * @return \Illuminate\Http\JsonResponse A JSON response containing the error message, data (if provided), and the specified HTTP status code.
     */
    public function sendError($error, $errorMessages = [], $code = Response::HTTP_BAD_REQUEST, $errorLog = null): JsonResponse
    {
        if ($errorLog) {
            Log::error($errorLog->getMessage());
        }

    	$response = [
            'success' => false,
            'message' => $error ?? [],
        ];

        $response['data'] = [];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
