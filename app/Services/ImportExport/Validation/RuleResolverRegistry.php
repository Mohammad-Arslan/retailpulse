<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Validation;

use App\Services\ImportExport\Contracts\RuleResolver;
use App\Services\ImportExport\Validation\Resolvers\BooleanRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\DateRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\DecimalRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\EmailRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\ExistsInDbRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\InListRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\NullableRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\NumericRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\RegexRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\RequiredRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\StringRuleResolver;
use App\Services\ImportExport\Validation\Resolvers\UniqueInDbRuleResolver;
use InvalidArgumentException;

final class RuleResolverRegistry
{
    /** @var array<string, RuleResolver> */
    private array $resolvers = [];

    public function __construct()
    {
        $this->register('required', new RequiredRuleResolver);
        $this->register('nullable', new NullableRuleResolver);
        $this->register('string', new StringRuleResolver);
        $this->register('numeric', new NumericRuleResolver);
        $this->register('decimal', new DecimalRuleResolver);
        $this->register('email', new EmailRuleResolver);
        $this->register('boolean', new BooleanRuleResolver);
        $this->register('date', new DateRuleResolver);
        $this->register('in_list', new InListRuleResolver);
        $this->register('regex', new RegexRuleResolver);
        $this->register('exists_in_db', new ExistsInDbRuleResolver);
        $this->register('unique_in_db', new UniqueInDbRuleResolver);
    }

    public function register(string $name, RuleResolver $resolver): void
    {
        $this->resolvers[$name] = $resolver;
    }

    public function get(string $name): RuleResolver
    {
        if (! isset($this->resolvers[$name])) {
            throw new InvalidArgumentException("Unknown rule resolver: {$name}");
        }

        return $this->resolvers[$name];
    }
}
