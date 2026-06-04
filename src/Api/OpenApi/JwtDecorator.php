<?php

namespace App\Api\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Decorates the OpenAPI schema to add JWT Bearer authentication.
 *
 * This ensures Swagger UI uses the "http/bearer" scheme, which automatically
 * prepends "Bearer " to the token — no need for users to type it manually.
 */
final class JwtDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $securitySchemes = $openApi->getComponents()->getSecuritySchemes() ?: new \ArrayObject();

        $securitySchemes['JWT'] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Enter your JWT token (without the "Bearer " prefix — it will be added automatically).',
        ]);

        $openApi = $openApi->withComponents(
            $openApi->getComponents()->withSecuritySchemes($securitySchemes)
        );

        // Apply JWT security globally to all operations
        $security = $openApi->getSecurity();
        $security[] = ['JWT' => []];
        $openApi = $openApi->withSecurity($security);

        return $openApi;
    }
}
