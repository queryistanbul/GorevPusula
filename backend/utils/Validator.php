<?php
/**
 * Input Validator
 * 
 * Validation utilities for request data
 */

class Validator
{
    private $errors = [];

    /**
     * Validate required field
     */
    public function required($field, $value, $message = null)
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[] = [
                'field' => $field,
                'message' => $message ?? "$field is required"
            ];
        }
        return $this;
    }

    /**
     * Validate integer
     */
    public function isInt($field, $value, $message = null)
    {
        if ($value !== null && !is_numeric($value)) {
            $this->errors[] = [
                'field' => $field,
                'message' => $message ?? "$field must be an integer"
            ];
        }
        return $this;
    }

    /**
     * Validate email
     */
    public function isEmail($field, $value, $message = null)
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = [
                'field' => $field,
                'message' => $message ?? "$field must be a valid email"
            ];
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $message = null)
    {
        if ($value !== null && strlen($value) < $min) {
            $this->errors[] = [
                'field' => $field,
                'message' => $message ?? "$field must be at least $min characters"
            ];
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $message = null)
    {
        if ($value !== null && strlen($value) > $max) {
            $this->errors[] = [
                'field' => $field,
                'message' => $message ?? "$field must not exceed $max characters"
            ];
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function isDate($field, $value, $format = 'Y-m-d', $message = null)
    {
        if ($value !== null) {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->errors[] = [
                    'field' => $field,
                    'message' => $message ?? "$field must be a valid date"
                ];
            }
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails()
    {
        return !$this->passes();
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Reset validator
     */
    public function reset()
    {
        $this->errors = [];
        return $this;
    }
}
