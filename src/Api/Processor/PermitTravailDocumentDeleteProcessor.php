<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\PermitTravail\Entity\PermitTravailDocument;
use App\Domain\PermitTravail\Service\PermitDocumentAccessGuard;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PermitTravailDocumentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $storage,
        private readonly PermitDocumentAccessGuard $accessGuard,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof PermitTravailDocument) {
            $permit = $data->getPermitTravail();
            $type = $data->getType();
            if ($permit !== null && $type !== null) {
                $this->accessGuard->assertCanModify($permit, $type);
            }

            $path = $data->getFilePath();
            if ($path !== null && $path !== '') {
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
