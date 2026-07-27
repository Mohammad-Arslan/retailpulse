<?php

declare(strict_types=1);

namespace Tests\Unit\ImportExport;

use App\Services\ImportExport\ImportContext;
use App\Services\ImportExport\Validation\Resolvers\UniqueInDbRuleResolver;
use InvalidArgumentException;
use Tests\TestCase;

final class UniqueInDbRuleResolverTest extends TestCase
{
    private function context(): ImportContext
    {
        return new ImportContext(
            jobId: 1,
            tenantId: null,
            userId: 1,
            mode: 'create',
            isDryRun: false,
            filePath: 'irrelevant.csv',
            disk: 'local',
            options: [],
        );
    }

    private function invoke(array $resolved, mixed $value): bool
    {
        $failed = false;

        foreach ($resolved as $rule) {
            if ($rule instanceof \Closure) {
                $rule('rows.0.field', $value, function () use (&$failed): void {
                    $failed = true;
                });
            }
        }

        return $failed;
    }

    public function test_throws_when_table_is_missing(): void
    {
        $resolver = new UniqueInDbRuleResolver;

        $this->expectException(InvalidArgumentException::class);

        $resolver->resolve(['column' => 'code'], $this->context(), [['code' => 'x']]);
    }

    public function test_second_occurrence_of_the_same_value_in_the_file_is_flagged_as_duplicate(): void
    {
        $resolver = new UniqueInDbRuleResolver;
        $context = $this->context();

        $first = $resolver->resolve(['table' => 'brands', 'column' => 'slug'], $context, [['code' => 'acme']]);
        $second = $resolver->resolve(['table' => 'brands', 'column' => 'slug'], $context, [['code' => 'acme']]);

        $this->assertFalse($this->invoke($first, 'acme'), 'First occurrence in the file must not be flagged.');
        $this->assertTrue($this->invoke($second, 'acme'), 'Second occurrence of the same value must be flagged.');
    }

    public function test_dedupe_state_is_scoped_to_the_context_not_the_resolver(): void
    {
        $resolver = new UniqueInDbRuleResolver;

        // A fresh ImportContext (as created per job run) must not see the previous
        // job's in-file duplicates — RuleResolverRegistry/UniqueInDbRuleResolver are
        // bound as singletons for the life of the queue worker, so per-job state
        // must live on the context, not the resolver.
        $jobOne = $this->context();
        $jobTwo = $this->context();

        $resolver->resolve(['table' => 'brands', 'column' => 'slug'], $jobOne, [['code' => 'acme']]);
        $resolvedAgain = $resolver->resolve(['table' => 'brands', 'column' => 'slug'], $jobTwo, [['code' => 'acme']]);

        $this->assertFalse($this->invoke($resolvedAgain, 'acme'));
    }

    public function test_composite_duplicate_requires_matching_sibling_values(): void
    {
        $resolver = new UniqueInDbRuleResolver;
        $context = $this->context();

        $ruleDef = [
            'table' => 'inventories',
            'column' => 'warehouse_id',
            'composite' => [
                ['column' => 'product_variant_id', 'field' => 'variant'],
                ['column' => 'batch_id', 'field' => 'batch'],
            ],
        ];

        $rowA = ['variant' => 'v1', 'batch' => 'b1'];
        $rowB = ['variant' => 'v2', 'batch' => 'b1'];

        $first = $resolver->resolve($ruleDef, $context, [$rowA]);
        $sameTuple = $resolver->resolve($ruleDef, $context, [$rowA]);
        $differentSibling = $resolver->resolve($ruleDef, $context, [$rowB]);

        $this->assertFalse($this->invoke($first, 'w1'));
        $this->assertTrue(
            $this->invoke($sameTuple, 'w1'),
            'Same warehouse + variant + batch tuple must be flagged as an in-file duplicate.',
        );
        $this->assertFalse(
            $this->invoke($differentSibling, 'w1'),
            'A different sibling (variant) value must not be treated as the same tuple.',
        );
    }
}
