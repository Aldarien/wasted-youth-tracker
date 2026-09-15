<?php

namespace Zieren\WYT\Application\ViewModel;

final class DashboardView
{
    /**
     * @param array<int, array{user: string, from: string, to: string, class: string, title: string}> $recentActivity
     * @param array<int, array{user: string, seconds: int, title: string, lastSeen: string}> $unclassified
     * @param array<string, ChildStatusView> $children
     */
    public function __construct(
        public readonly SetupProgressView $setup,
        public readonly int $userCount,
        public readonly int $classCount,
        public readonly int $totalLimitCount,
        public readonly array $recentActivity,
        public readonly array $unclassified,
        public readonly array $children
    ) {
    }
}
