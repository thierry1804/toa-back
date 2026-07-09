<?php

declare(strict_types=1);

namespace App\Domain\PlanPrevention\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GotenbergPdfService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $gotenbergUrl,
    ) {}

    public function htmlToPdf(string $htmlContent): string
    {
        $formData = new FormDataPart([
            'files' => new DataPart($htmlContent, 'index.html', 'text/html'),
            'landscape' => 'false',
            'printBackground' => 'true',
            'marginTop' => '1',
            'marginBottom' => '1',
            'marginLeft' => '0.5',
            'marginRight' => '0.5',
        ]);

        $response = $this->httpClient->request(
            'POST',
            rtrim($this->gotenbergUrl, '/') . '/forms/chromium/convert/html',
            [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body'    => $formData->bodyToIterable(),
                'timeout' => 60,
            ],
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('Gotenberg error %d: %s', $response->getStatusCode(), $response->getContent(false)),
            );
        }

        return $response->getContent();
    }
}
