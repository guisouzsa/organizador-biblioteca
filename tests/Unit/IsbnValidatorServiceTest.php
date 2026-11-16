<?php

namespace Tests\Unit;

use App\Services\Catalog\IsbnValidatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsbnValidatorServiceTest extends TestCase
{
    private IsbnValidatorService $isbn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isbn = new IsbnValidatorService;
    }

    public function test_valid_isbn13(): void
    {
        $this->assertTrue($this->isbn->isValidIsbn13('9780306406157'));
        $this->assertTrue($this->isbn->isValidIsbn13('9791090636071'));
    }

    public function test_invalid_isbn13_check_digit(): void
    {
        $this->assertFalse($this->isbn->isValidIsbn13('9780306406158'));
    }

    public function test_ean13_without_isbn_prefix_is_not_isbn(): void
    {
        // EAN-13 com dígito verificador correto, mas prefixo 789 (produto comum).
        $this->assertFalse($this->isbn->isValidIsbn13('7891000100103'));
        $parsed = $this->isbn->parse('7891000100103');
        $this->assertFalse($parsed['valid']);
        $this->assertStringContainsString('não é um ISBN', $parsed['error']);
    }

    public function test_valid_isbn10_including_x_check_digit(): void
    {
        $this->assertTrue($this->isbn->isValidIsbn10('0306406152'));
        $this->assertTrue($this->isbn->isValidIsbn10('080442957X'));
        $this->assertFalse($this->isbn->isValidIsbn10('0306406153'));
    }

    public function test_conversion_between_isbn10_and_isbn13(): void
    {
        $this->assertSame('9780306406157', $this->isbn->toIsbn13('0306406152'));
        $this->assertSame('0306406152', $this->isbn->toIsbn10('9780306406157'));
        $this->assertSame('080442957X', $this->isbn->toIsbn10('9780804429573'));
        $this->assertNull($this->isbn->toIsbn13('0306406153'));
    }

    public function test_isbn13_with_979_prefix_has_no_isbn10(): void
    {
        $this->assertNull($this->isbn->toIsbn10('9791090636071'));
    }

    #[DataProvider('normalizationProvider')]
    public function test_normalization(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->isbn->normalize($input));
    }

    public static function normalizationProvider(): array
    {
        return [
            'hifens' => ['978-0-306-40615-7', '9780306406157'],
            'prefixo ISBN' => ['ISBN 978 0 306 40615 7', '9780306406157'],
            'prefixo ISBN-13' => ['ISBN-13: 978-0-306-40615-7', '9780306406157'],
            'x minúsculo' => ['0-8044-2957-x', '080442957X'],
            'espaços nas pontas' => ["  9780306406157\n", '9780306406157'],
        ];
    }

    public function test_parse_isbn10_returns_isbn13(): void
    {
        $parsed = $this->isbn->parse('0-306-40615-2');
        $this->assertTrue($parsed['valid']);
        $this->assertSame('9780306406157', $parsed['isbn13']);
        $this->assertSame('0306406152', $parsed['isbn10']);
    }

    public function test_parse_rejects_invalid_values(): void
    {
        $this->assertFalse($this->isbn->parse('')['valid']);
        $this->assertFalse($this->isbn->parse('12345')['valid']);
        $this->assertFalse($this->isbn->parse('abc')['valid']);
        $this->assertFalse($this->isbn->parse('9780306406158')['valid']);
        $this->assertFalse($this->isbn->parse('0306406153')['valid']);
    }
}
