<?php

declare(strict_types=1);

namespace Sidetours\Compass\Tests\Fixtures\promoted_properties\failing;

interface RepoInterface {}
interface ValidatorInterface {}

final class TwoParams
{
    private RepoInterface $repository;

    private ValidatorInterface $validator;

    public function __construct(
        RepoInterface $repository,
        ValidatorInterface $validator,
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
    }
}
