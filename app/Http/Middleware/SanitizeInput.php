<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Sanitize all input data
        $input = $request->all();

        foreach ($input as $key => $value) {
            // Skip file uploads
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            // Sanitize string values
            if (is_string($value)) {
                $input[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $input[$key] = $this->sanitizeArray($value);
            }
        }

        // Replace the request input with sanitized data
        $request->merge($input);

        return $next($request);
    }

    /**
     * Sanitize a string value.
     *
     * @param  string  $value
     * @return string
     */
    protected function sanitizeString($value)
    {
        // Trim whitespace
        $value = trim($value);

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', $value);

        // Remove control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // HTML entity encoding (prevent XSS)
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $value;
    }

    /**
     * Sanitize an array of values.
     *
     * @param  array  $array
     * @return array
     */
    protected function sanitizeArray(array $array)
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return $this->sanitizeString($value);
            } elseif (is_array($value)) {
                return $this->sanitizeArray($value);
            }
            return $value;
        }, $array);
    }
}
