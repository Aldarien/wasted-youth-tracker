<?php

namespace Zieren\WYT\Application\ViewModel;

use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Entity\User;

final class AdminPageView
{
    /**
     * @param User[] $users
     * @param ActivityClass[] $classes
     * @param array<int, list<Classification>> $classifications
     * @param array<string, string> $globalConfig
     * @param array<string, array<string, string>> $userConfig
     * @param array<string, array<int, array<string, mixed>>> $limits
     */
    public function __construct(
        public readonly array $users,
        public readonly array $classes,
        public readonly array $classifications,
        public readonly array $globalConfig,
        public readonly array $userConfig,
        public readonly array $limits,
        public readonly DashboardView $dashboard
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'users' => $this->users,
            'classes' => $this->classes,
            'classifications' => $this->classifications,
            'globalConfig' => $this->globalConfig,
            'userConfig' => $this->userConfig,
            'limits' => $this->limits,
            'dashboard' => $this->dashboard,
        ];
    }
}
