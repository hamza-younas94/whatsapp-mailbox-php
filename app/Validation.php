<?php

namespace App;

/**
 * Form Validation Class
 * Provides comprehensive frontend and backend validation
 */
class Validation
{
    /**
     * Validation rules map
     */
    private static $rules = [];
    
    /**
     * Validation messages
     */
    private static $messages = [];
    
    /**
     * Validation errors
     */
    private $errors = [];
    
    /**
     * Data being validated
     */
    private $data = [];
    
    /**
     * Constructor
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    
    /**
     * Validate data against rules
     */
    public function validate(array $rules): bool
    {
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;
            
            foreach ($ruleList as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply a single validation rule
     */
    private function applyRule($field, $value, $rule): void
    {
        // Parse rule with parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $ruleParam = $parts[1] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;
                
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;
                
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} must be a valid URL.");
                }
                break;
                
            case 'min':
                if ($value && strlen($value) < (int)$ruleParam) {
                    $this->addError($field, "The {$field} must be at least {$ruleParam} characters.");
                }
                break;
                
            case 'max':
                if ($value && strlen($value) > (int)$ruleParam) {
                    $this->addError($field, "The {$field} must not exceed {$ruleParam} characters.");
                }
                break;
                
            case 'numeric':
                if ($value && !is_numeric($value)) {
                    $this->addError($field, "The {$field} must be a number.");
                }
                break;
                
            case 'integer':
                if ($value && !is_int($value) && !ctype_digit((string)$value)) {
                    $this->addError($field, "The {$field} must be an integer.");
                }
                break;
                
            case 'phone':
                // Basic phone validation: 10-15 digits
                if ($value && !preg_match('/^\+?[\d\s\-()]{10,15}$/', $value)) {
                    $this->addError($field, "The {$field} must be a valid phone number.");
                }
                break;
                
            case 'regex':
                if ($value && !preg_match($ruleParam, $value)) {
                    $this->addError($field, "The {$field} format is invalid.");
                }
                break;
                
            case 'unique':
                // $ruleParam format: "table,column"
                [$table, $column] = explode(',', $ruleParam);
                if ($value && $this->recordExists($table, $column, $value)) {
                    $this->addError($field, "The {$field} has already been taken.");
                }
                break;
                
            case 'confirmed':
                // Check if confirmation field exists and matches
                $confirmField = $field . '_confirmation';
                if ($value !== ($this->data[$confirmField] ?? null)) {
                    $this->addError($field, "The {$field} confirmation does not match.");
                }
                break;
                
            case 'in':
                // $ruleParam is comma-separated list
                $allowedValues = explode(',', $ruleParam);
                if ($value && !in_array($value, array_map('trim', $allowedValues), true)) {
                    $this->addError($field, "The {$field} has an invalid value.");
                }
                break;
                
            case 'array':
                if ($value && !is_array($value)) {
                    $this->addError($field, "The {$field} must be an array.");
                }
                break;
                
            case 'string':
                if ($value && !is_string($value)) {
                    $this->addError($field, "The {$field} must be a string.");
                }
                break;
        }
    }
    
    /**
     * Check if record exists in database
     */
    private function recordExists($table, $column, $value): bool
    {
        try {
            $result = \Illuminate\Database\Capsule\Manager::table($table)
                ->where($column, $value)
                ->exists();
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Add error to collection
     */
    private function addError($field, $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get all errors
     */
    public function errors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get first error
     */
    public function first($field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Check if field has errors
     */
    public function hasError($field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }
    
    /**
     * Get JavaScript validation object for frontend
     * Returns data suitable for browser-side validation
     */
    public static function getRulesJSON(array $rules): string
    {
        $jsRules = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $jsRules[$field] = $ruleList;
        }
        return json_encode($jsRules);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
