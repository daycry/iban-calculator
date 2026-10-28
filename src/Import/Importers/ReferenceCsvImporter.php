<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\Registry\IsoCountryRegistry;
use InvalidArgumentException;

/**
 * Local, annotated bank-reference CSV supplied by the consuming application.
 * Reads and validates the whole file before returning any importer so malformed
 * trailing rows cannot cause a partially applied import. No bank data is bundled
 * and no network is accessed. Each row retains its original version and license.
 */
final class ReferenceCsvImporter implements ImporterInterface
{
    /** @param list<array<string, string|null>> $records */
    private function __construct(private string $country, private array $records)
    {
    }

    /** @return list<self> One importer per country, in file order. */
    public static function fromFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('The bank-reference CSV must be a readable local file.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Cannot open the bank-reference CSV.');
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (!is_array($header)) {
                throw new InvalidArgumentException('The bank-reference CSV is empty.');
            }
            $header = array_map(static fn (?string $cell): string => trim($cell ?? ''), $header);
            $header[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $required = ['country_code', 'bank_code', 'branch_code', 'bic', 'name', 'source_version', 'source_license'];
            if (count(array_unique($header)) !== count($header) || array_diff($required, $header) !== []) {
                throw new InvalidArgumentException('The bank-reference CSV has missing or duplicate column names.');
            }

            $countries = new IsoCountryRegistry();
            $bics = new BicValidator($countries);
            /** @var array<string, list<array<string, string|null>>> $grouped */
            $grouped = [];
            /** @var array<string, true> $seen */
            $seen = [];
            $line = 1;
            while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) !== count($header)) {
                    throw new InvalidArgumentException(sprintf('Bank-reference CSV record %d has the wrong column count.', $line));
                }
                $row = array_combine($header, array_map(static fn (?string $cell): string => trim($cell ?? ''), $cells));
                foreach ($required as $field) {
                    if (!mb_check_encoding($row[$field], 'UTF-8')) {
                        throw new InvalidArgumentException(sprintf('Invalid UTF-8 in bank-reference CSV record %d.', $line));
                    }
                }
                $country = strtoupper($row['country_code']);
                if (!$countries->has($country) && $country !== 'XK') {
                    throw new InvalidArgumentException(sprintf('Unknown country in bank-reference CSV record %d.', $line));
                }
                $bank = strtoupper($row['bank_code']);
                $branch = strtoupper($row['branch_code']);
                if (preg_match('/^[A-Z0-9]{1,35}$/D', $bank) !== 1
                    || ($branch !== '' && preg_match('/^[A-Z0-9]{1,35}$/D', $branch) !== 1)) {
                    throw new InvalidArgumentException(sprintf('Invalid bank/branch code in bank-reference CSV record %d.', $line));
                }
                foreach (['name' => 255, 'source_version' => 64, 'source_license' => 64] as $field => $limit) {
                    if ($row[$field] === '' || mb_strlen($row[$field], 'UTF-8') > $limit) {
                        throw new InvalidArgumentException(sprintf('Missing or oversized %s in bank-reference CSV record %d.', $field, $line));
                    }
                }
                $bic = $bics->normalize($row['bic']);
                if ($bic !== '' && !$bics->isValid($bic)) {
                    throw new InvalidArgumentException(sprintf('Invalid BIC in bank-reference CSV record %d.', $line));
                }
                $key = $country . ':' . $bank . ':' . $branch;
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException(sprintf('Duplicate bank/branch key in bank-reference CSV record %d.', $line));
                }
                $seen[$key] = true;
                $record = [
                    'bank_code' => $bank,
                    'branch_code' => $branch === '' ? null : $branch,
                    'name' => $row['name'],
                    'source_version' => $row['source_version'],
                    'source_license' => $row['source_license'],
                ];
                // An empty reference BIC supplies no information; it must not erase a known one.
                if ($bic !== '') {
                    $record['bic'] = $bic;
                }
                $grouped[$country][] = $record;
            }
            if ($grouped === []) {
                throw new InvalidArgumentException('The bank-reference CSV contains no bank rows.');
            }
            $importers = [];
            foreach ($grouped as $country => $records) {
                $importers[] = new self($country, $records);
            }

            return $importers;
        } finally {
            fclose($handle);
        }
    }

    public function countryCode(): string
    {
        return $this->country;
    }

    public function sourceId(): string
    {
        return 'reference-csv';
    }

    public function sourceName(): string
    {
        return 'Operator-supplied bank reference CSV';
    }

    public function license(): string
    {
        return 'See per-row source_license';
    }

    public function sourceUrl(): string
    {
        return 'Local file (--file); attribution in source_version/source_license';
    }

    public function rows(?string $localFile = null): iterable
    {
        if ($localFile !== null) {
            foreach (self::fromFile($localFile) as $importer) {
                if ($importer->countryCode() === $this->country) {
                    yield from $importer->rows();
                }
            }

            return;
        }

        yield from $this->records;
    }
}
