<?php

namespace Custom\Router\Middleware;

use Custom\Router\Interfaces\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Input Validation Middleware
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules = [];
    private array $messages = [];

    public function __construct(array $rules = [], array $messages = [])
    {
        $this->rules = $rules;
        $this->messages = array_merge([
            'required' => 'The {field} field is required.',
            'email' => 'The {field} field must be a valid email address.',
            'min' => 'The {field} field must be at least {min} characters.',
            'max' => 'The {field} field must not exceed {max} characters.',
            'numeric' => 'The {field} field must be numeric.',
            'alpha' => 'The {field} field must contain only letters.',
            'alphanumeric' => 'The {field} field must contain only letters and numbers.',
            'url' => 'The {field} field must be a valid URL.',
            'regex' => 'The {field} field format is invalid.',
        ], $messages);
    }

    public function handle(Request $request, callable $next): Response
    {
        $routeName = $request->attributes->get('_route');
        
        if (!isset($this->rules[$routeName])) {
            return $next($request);
        }

        $validation = $this->validate($request, $this->rules[$routeName]);
        
        if (!$validation['valid']) {
            return new Response(
                json_encode([
                    'error' => 'Validation failed',
                    'errors' => $validation['errors']
                ]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['Content-Type' => 'application/json']
            );
        }

        return $next($request);
    }

    /**
     * Validate request data against rules
     */
    private function validate(Request $request, array $rules): array
    {
        $data = array_merge(
            $request->request->all(),
            $request->query->all(),
            $request->attributes->all()
        );

        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                $error = $this->validateField($field, $data[$field] ?? null, $rule);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate single field against rule
     */
    private function validateField(string $field, mixed $value, string $rule): ?string
    {
        [$ruleName, $parameters] = $this->parseRule($rule);

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    return $this->getMessage('required', ['field' => $field]);
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->getMessage('email', ['field' => $field]);
                }
                break;

            case 'min':
                if ($value && strlen($value) < (int)$parameters[0]) {
                    return $this->getMessage('min', ['field' => $field, 'min' => $parameters[0]]);
                }
                break;

            case 'max':
                if ($value && strlen($value) > (int)$parameters[0]) {
                    return $this->getMessage('max', ['field' => $field, 'max' => $parameters[0]]);
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    return $this->getMessage('numeric', ['field' => $field]);
                }
                break;

            case 'alpha':
                if ($value && !ctype_alpha($value)) {
                    return $this->getMessage('alpha', ['field' => $field]);
                }
                break;

            case 'alphanumeric':
                if ($value && !ctype_alnum($value)) {
                    return $this->getMessage('alphanumeric', ['field' => $field]);
                }
                break;

            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return $this->getMessage('url', ['field' => $field]);
                }
                break;

            case 'regex':
                if ($value && !preg_match($parameters[0], $value)) {
                    return $this->getMessage('regex', ['field' => $field]);
                }
                break;
        }

        return null;
    }

    /**
     * Parse validation rule
     */
    private function parseRule(string $rule): array
    {
        if (strpos($rule, ':') !== false) {
            [$name, $params] = explode(':', $rule, 2);
            return [$name, explode(',', $params)];
        }

        return [$rule, []];
    }

    /**
     * Get validation message
     */
    private function getMessage(string $rule, array $replacements = []): string
    {
        $message = $this->messages[$rule] ?? "The {field} field is invalid.";
        
        foreach ($replacements as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Add validation rules for route
     */
    public function addRules(string $routeName, array $rules): void
    {
        $this->rules[$routeName] = $rules;
    }

    /**
     * Add custom validation messages
     */
    public function addMessages(array $messages): void
    {
        $this->messages = array_merge($this->messages, $messages);
    }
}