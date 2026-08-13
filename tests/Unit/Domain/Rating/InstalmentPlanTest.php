<?php

declare(strict_types=1);

use App\Domain\Rating\InstalmentPlan;

it('matches the AT-01 instalment calculation', function () {
    $plan = InstalmentPlan::calculate(32775);

    expect($plan['deposit_pence'])->toBe(6555)
        ->and($plan['balance_pence'])->toBe(26220)
        ->and($plan['credit_charge_pence'])->toBe(3278)
        ->and($plan['financed_amount_pence'])->toBe(29498)
        ->and($plan['instalments'][0])->toBe(2688)
        ->and($plan['instalments'][1])->toBe(2681)
        ->and($plan['instalments'][10])->toBe(2681)
        ->and($plan['total_by_instalments_pence'])->toBe(36053);
});

it('always allocates the residual explicitly', function () {
    $plan = InstalmentPlan::calculate(10001);

    expect(
        array_sum($plan['instalments'])
    )->toBe($plan['financed_amount_pence']);

    expect(
        $plan['deposit_pence'] +
        array_sum($plan['instalments'])
    )->toBeGreaterThan(0);
});