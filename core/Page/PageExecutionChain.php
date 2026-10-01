<?php

declare(strict_types=1);

namespace Core\Page;

use LogicException;
use RuntimeException;
use Core\View\SafeHtml;

final class PageExecutionChain
{
    private const MAX_STAGES = 32;

    /** @var list<PageExecutionStageInterface> */
    private array $stages;

    private int $cursor = 0;
    private ?int $activeStage = null;

    /** @var array<int, true> */
    private array $continuedStages = [];

    private bool $started = false;

    /** @param list<PageExecutionStageInterface> $stages */
    public function __construct(array $stages)
    {
        if ($stages === []) {
            throw new RuntimeException('Page execution chain must contain at least one stage.');
        }

        if (count($stages) > self::MAX_STAGES) {
            throw new RuntimeException(sprintf(
                'Page execution chain exceeds the maximum depth of %d stages.',
                self::MAX_STAGES,
            ));
        }

        foreach ($stages as $stage) {
            if (!$stage instanceof PageExecutionStageInterface) {
                throw new RuntimeException('Page execution chain contains an invalid stage.');
            }
        }

        $this->stages = array_values($stages);
    }

    public function start(PageRuntime $page): SafeHtml
    {
        if ($this->started) {
            throw new LogicException('Page execution chain has already been started.');
        }

        $this->started = true;

        return $this->invokeNext($page);
    }

    public function continue(PageRuntime $page): SafeHtml
    {
        if (!$this->started || $this->activeStage === null) {
            throw new LogicException('Page execution continuation is only available while a stage is running.');
        }

        $caller = $this->activeStage;
        if (isset($this->continuedStages[$caller])) {
            throw new LogicException('A page execution stage may continue the chain only once.');
        }

        $this->continuedStages[$caller] = true;

        return $this->invokeNext($page);
    }

    private function invokeNext(PageRuntime $page): SafeHtml
    {
        $index = $this->cursor;
        $stage = $this->stages[$index] ?? null;
        if (!$stage instanceof PageExecutionStageInterface) {
            throw new RuntimeException('Page execution chain has no remaining stage.');
        }

        ++$this->cursor;
        $previousActiveStage = $this->activeStage;
        $this->activeStage = $index;

        try {
            return $stage->execute($page);
        } finally {
            $this->activeStage = $previousActiveStage;
        }
    }
}
