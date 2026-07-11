<?php

declare(strict_types=1);

namespace Tests\Import;

use Daycry\Iban\Import\ImportReport;
use PHPUnit\Framework\TestCase;

/**
 * Plain-value-object test for the `ImportReport` DTO (V-6): framework-free,
 * no CI4 bootstrap needed (mirrors `tests/DTO/BankResultTest.php` etc.).
 *
 * @see \Daycry\Iban\Import\ImportReport
 */
final class ImportReportTest extends TestCase
{
    public function testConstructorExposesAllFieldsReadOnly(): void
    {
        $report = new ImportReport(
            countryCode: 'AT',
            sourceId: 'fake',
            fetched: 3,
            imported: 2,
            skipped: 1,
            dryRun: false,
            messages: ['Skipped row #2: missing or empty "bank_code".'],
        );

        self::assertSame('AT', $report->countryCode);
        self::assertSame('fake', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(1, $report->skipped);
        self::assertFalse($report->dryRun);
        self::assertSame(['Skipped row #2: missing or empty "bank_code".'], $report->messages);
    }

    public function testMessagesDefaultsToAnEmptyArrayWhenOmitted(): void
    {
        $report = new ImportReport(
            countryCode: 'AT',
            sourceId: 'fake',
            fetched: 0,
            imported: 0,
            skipped: 0,
            dryRun: true,
        );

        self::assertSame([], $report->messages);
        self::assertTrue($report->dryRun);
    }
}
