<?php

namespace Zieren\WYT\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Exception\ClassificationNotFoundException;
use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;
use Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Domain\ValueObject\ClassificationResult;

final class ClassificationServiceTest extends TestCase
{
    public function testClassifiesTitlesToMappedLimits(): void
    {
        $classificationRepository = $this->createStub(ClassificationRepositoryInterface::class);
        $classificationRepository->method('findBestMatch')
            ->willReturnMap([
                ['mail', new Classification(1, 10, 5, '/mail/i')],
                ['game', new Classification(2, 20, 5, '/game/i')],
            ]);

        $mappingRepository = $this->createStub(ClassLimitMappingRepositoryInterface::class);
        $mappingRepository->method('findLimitIdsByClassAndUser')
            ->willReturnMap([
                [10, 'u1', [100, 101]],
                [20, 'u1', [102]],
            ]);

        $service = new ClassificationService($classificationRepository, $mappingRepository);
        $results = $service->classify('u1', ['mail', 'game']);

        $this->assertEquals([
            new ClassificationResult(10, [100, 101]),
            new ClassificationResult(20, [102]),
        ], $results);
    }

    public function testThrowsWhenNoClassificationMatches(): void
    {
        $classificationRepository = $this->createStub(ClassificationRepositoryInterface::class);
        $classificationRepository->method('findBestMatch')->willReturn(null);

        $mappingRepository = $this->createStub(ClassLimitMappingRepositoryInterface::class);

        $service = new ClassificationService($classificationRepository, $mappingRepository);

        $this->expectException(ClassificationNotFoundException::class);
        $service->classify('u1', ['unknown']);
    }
}
