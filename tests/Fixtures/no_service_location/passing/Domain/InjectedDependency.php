<?php

declare(strict_types=1);

final class InjectedDependency
{
    public function __construct(private readonly \stdClass $repository)
    {
    }

    public function run(): \stdClass
    {
        return $this->repository;
    }
}
