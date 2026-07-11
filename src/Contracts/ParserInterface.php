<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Exceptions\InvalidIbanException;

/**
 * Parser contract for IBAN parsing and formatting operations.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface ParserInterface
{
    /**
     * Normalize an IBAN string (remove spaces, uppercase).
     *
     * @param string $iban The IBAN to normalize.
     *
     * @return string The normalized IBAN.
     */
    public function normalize(string $iban): string;

    /**
     * Parse an IBAN string into a ParsedIban object.
     *
     * Throws an exception if parsing is impossible.
     *
     * @param string $iban The IBAN to parse.
     *
     * @return ParsedIban The parsed IBAN object.
     *
     * @throws InvalidIbanException If the IBAN cannot be parsed.
     */
    public function parse(string $iban): ParsedIban;

    /**
     * Try to parse an IBAN string leniently.
     *
     * Returns null if parsing fails, rather than throwing an exception.
     *
     * @param string $iban The IBAN to parse.
     *
     * @return ?ParsedIban The parsed IBAN object, or null if parsing fails.
     */
    public function tryParse(string $iban): ?ParsedIban;

    /**
     * Format a ParsedIban or IBAN string in the specified format.
     *
     * @param string|ParsedIban $iban The IBAN to format (string or parsed object).
     * @param IbanFormat        $f    The format to use (defaults to Print format).
     *
     * @return string The formatted IBAN string.
     */
    public function format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string;
}
