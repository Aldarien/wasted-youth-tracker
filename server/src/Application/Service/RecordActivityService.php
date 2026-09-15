<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Clock;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Entity\ActivityRecord;
use Zieren\WYT\Domain\Repository\ActivityRecordRepositoryInterface;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;
use Zieren\WYT\Domain\Repository\TransactionManagerInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Domain\ValueObject\ClassificationResult;

class RecordActivityService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ClassificationService $classificationService,
        private readonly ActivityRecordRepositoryInterface $activityRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly TransactionManagerInterface $transactionManager
    ) {
    }

    /**
     * @param string[] $titles
     * @return ClassificationResult[]
     */
    public function execute(string $userId, string $lastError, array $titles): array
    {
        return $this->transactionManager->run(
            fn (): array => $this->executeWithinTransaction($userId, $lastError, $titles)
        );
    }

    /**
     * @param string[] $titles
     * @return ClassificationResult[]
     */
    private function executeWithinTransaction(string $userId, string $lastError, array $titles): array
    {
        if ($lastError) {
            $this->userRepository->updateLastError($userId, $lastError);
        }

        $ts = $this->clock->now()->getTimestamp();
        $titles = array_map(fn ($t) => substr($t, 0, 256), $titles);

        if (!$titles) {
            $titles = [''];
            $classifications = [new ClassificationResult(Defaults::DEFAULT_CLASS_ID, [])];
            $isPseudo = true;
        } else {
            $isPseudo = false;
            $classifications = $this->classificationService->classify($userId, $titles);
            foreach ($titles as $i => $title) {
                if (!$title) {
                    $titles[$i] = '(no title)';
                }
            }
        }

        $classificationsMap = [];
        $newTitles = [];
        foreach ($classifications as $i => $classification) {
            $title = $titles[$i];
            $titleLowerCase = strtolower($title);
            $classificationsMap[$titleLowerCase] = [
                'class_id' => $classification->classId,
                'title' => $title,
            ];
            $newTitles[$titleLowerCase] = $title;
        }

        $seq = ($this->activityRepository->getMaxSequence($userId) ?? -1) + 1;
        $previousSeq = $seq - 1;

        $maxInterval = $this->getMaxInterval($userId);
        $recent = $this->activityRepository->findRecentByTitles(
            $userId,
            $previousSeq,
            $ts - $maxInterval,
            $titles
        );
        if ($recent) {
            $records = [];
            foreach ($recent as $row) {
                $title = $row['title'];
                $titleLowerCase = strtolower($title);
                $records[] = new ActivityRecord(
                    $userId,
                    $seq,
                    $row['from_ts'],
                    $ts,
                    $classificationsMap[$titleLowerCase]['class_id'],
                    $title
                );
                unset($newTitles[$titleLowerCase]);
            }
            $this->activityRepository->saveBatch($records);
        }

        $this->activityRepository->concludePreviousRecords($userId, $previousSeq, $ts, $ts - $maxInterval);

        if ($newTitles) {
            $records = [];
            foreach ($newTitles as $titleLowerCase => $title) {
                $classId = $titleLowerCase === ''
                    ? Defaults::DEFAULT_CLASS_ID
                    : $classificationsMap[$titleLowerCase]['class_id'];
                $records[] = new ActivityRecord($userId, $seq, $ts, $ts, $classId, $title);
            }
            $this->activityRepository->saveBatch($records);
        }

        return $isPseudo ? [] : $classifications;
    }

    private function getMaxInterval(string $userId): int
    {
        return ($this->configRepository->getClientInt($userId, 'sample_interval_seconds') ?? 15) + 30;
    }
}
