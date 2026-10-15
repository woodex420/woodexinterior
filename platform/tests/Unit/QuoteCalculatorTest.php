<?php

use App\Domain\Sales\Data\LineInput;
use App\Domain\Sales\Enums\DiscountType;
use App\Domain\Sales\Enums\MilestonePlanKey;
use App\Domain\Sales\Services\MilestonePlan;
use App\Domain\Sales\Services\QuoteCalculator;
use App\Support\Money;

function rupees(int|string $amount): int
{
    return Money::ofRupees($amount)->minor;
}

it('totals the Renovation "Wood Work" section to the paisa', function () {
    // Real lines from the Woodex Renovation template (fractional quantities).
    $woodWork = [
        new LineInput('142.5', rupees(1200)),   // 171,000
        new LineInput('121.125', rupees(1200)), // 145,350
        new LineInput('142.5', rupees(1200)),   // 171,000
        new LineInput('102.125', rupees(1200)), // 122,550
        new LineInput('92.625', rupees(2800)),  // 259,350
        new LineInput('76', rupees(2800)),      // 212,800
        new LineInput('48', rupees(2200)),      // 105,600
    ];

    $totals = (new QuoteCalculator)->calculate([$woodWork]);

    expect($totals->sectionSubtotals[0])->toBe(rupees(1_187_650))
        ->and($totals->lines[0][1]['total'])->toBe(rupees(145_350))
        ->and($totals->total)->toBe(rupees(1_187_650))
        ->and($totals->rounding)->toBe(0);
});

it('applies line discounts, quote discount, other charges, tax and rounding in order', function () {
    $sections = [
        [
            new LineInput('1', rupees(50_000)),
            new LineInput('256', rupees(2_000), DiscountType::Percent, 1000), // 512,000 − 10% = 460,800
            new LineInput('2', rupees(35_000), DiscountType::Amount, rupees(5_000)), // 70,000 − 5,000 = 65,000
        ],
        [
            new LineInput('1000', rupees(120)), // 120,000
            new LineInput('1', rupees(99_999), isOptional: true), // shown, not counted
        ],
    ];

    $totals = (new QuoteCalculator)->calculate(
        sections: $sections,
        discountType: DiscountType::Percent,
        discountValue: 250,              // 2.5%
        otherChargesMinor: rupees(20_000),
        taxBp: 1600,                      // 16%
    );

    expect($totals->sectionSubtotals)->toBe([rupees(575_800), rupees(120_000)])
        ->and($totals->subtotal)->toBe(rupees(695_800))
        ->and($totals->discount)->toBe(rupees(17_395))          // 2.5% of 695,800
        ->and($totals->taxable)->toBe(rupees(698_405))          // 695,800 − 17,395 + 20,000
        ->and($totals->tax)->toBe(rupees('111744.80'))          // 16%
        ->and($totals->total)->toBe(rupees(810_150))            // 810,149.80 rounded to rupee
        ->and($totals->rounding)->toBe(20);                     // +20 paisa
});

it('caps fixed discounts at the base amount and computes margin from internal costs', function () {
    $totals = (new QuoteCalculator)->calculate(
        sections: [[new LineInput('10', rupees(1_000), internalCostMinor: rupees(600))]],
        discountType: DiscountType::Amount,
        discountValue: rupees(50_000),
    );

    expect($totals->discount)->toBe(rupees(10_000))
        ->and($totals->total)->toBe(0)
        ->and($totals->marginBp)->toBeNull(); // no revenue after discount

    $withMargin = (new QuoteCalculator)->calculate([[new LineInput('10', rupees(1_000), internalCostMinor: rupees(600))]]);
    expect($withMargin->marginBp)->toBe(4000); // 40%
});

it('rejects invalid quantities', function () {
    new LineInput('1.2345', 100);
})->throws(InvalidArgumentException::class);

it('splits execution 50/40/10 and design 75/25 so amounts always add up', function () {
    $total = Money::ofMinor(rupees(1_187_651));

    $execution = MilestonePlan::split($total, MilestonePlan::definition(MilestonePlanKey::Execution));
    expect(array_column($execution, 'amount_minor'))->toBe([59_382_550, 47_506_040, 11_876_510])
        ->and(array_sum(array_column($execution, 'amount_minor')))->toBe($total->minor);

    $design = MilestonePlan::split(Money::ofRupees(333_333), MilestonePlan::definition(MilestonePlanKey::Design));
    expect(array_sum(array_column($design, 'amount_minor')))->toBe(rupees(333_333))
        ->and($design[0]['amount_minor'])->toBe(rupees('249999.75'));
});

it('requires milestone percentages to total 100%', function () {
    MilestonePlan::split(Money::ofRupees(1000), [['name' => 'Advance', 'percent_bp' => 5000]]);
})->throws(InvalidArgumentException::class);
