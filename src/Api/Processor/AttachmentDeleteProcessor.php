<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AttachmentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (is_object($data) && method_exists($data, 'getFilePath')) {
            $path = $data->getFilePath();
            if (is_string($path) && $path !== '') {
                try {
                    $this->storage->delete($path);
                } catch (\Throwable) {
                    // Ignore missing file in storage
                }
            }
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
