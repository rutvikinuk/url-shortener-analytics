<?php

namespace Tests\Unit;

use App\Services\QrCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QrCodeTest extends TestCase
{
    /** Longest text (bytes, level M) each version can hold. */
    public static function versionBoundaries(): array
    {
        return [
            'v1 max' => [14, 21], 'v2 min' => [15, 25], 'v2 max' => [26, 25],
            'v3 min' => [27, 29], 'v3 max' => [42, 29], 'v4 min' => [43, 33],
            'v10 max' => [213, 57],
        ];
    }

    #[DataProvider('versionBoundaries')]
    public function test_matrix_size_follows_the_text_length(int $length, int $size): void
    {
        $this->assertCount($size, QrCode::matrix(str_repeat('a', $length)));
    }

    public function test_matrix_is_square_and_has_the_dark_module(): void
    {
        $m = QrCode::matrix('https://example.com/abc');
        $n = count($m);
        foreach ($m as $row) {
            $this->assertCount($n, $row);
        }
        // the always-dark module sits at (4*version + 9, 8)
        $version = ($n - 17) / 4;
        $this->assertTrue($m[4 * $version + 9][8]);
    }

    public function test_all_three_finder_patterns_are_present(): void
    {
        $m = QrCode::matrix('hello');
        $n = count($m);
        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$r, $c]) {
            $this->assertTrue($m[$r][$c] && $m[$r + 6][$c + 6] && $m[$r + 3][$c + 3]);
            $this->assertFalse($m[$r + 1][$c + 1]);
        }
    }

    public function test_different_text_gives_a_different_code(): void
    {
        $this->assertNotSame(QrCode::matrix('https://a.test/x'), QrCode::matrix('https://a.test/y'));
    }

    public function test_multibyte_text_is_measured_in_bytes(): void
    {
        $this->assertCount(21, QrCode::matrix('ü')); // 2 bytes
        $this->expectException(InvalidArgumentException::class);
        QrCode::matrix(str_repeat('ü', 107)); // 214 bytes
    }

    public function test_svg_is_well_formed_with_a_quiet_zone(): void
    {
        $svg = QrCode::svg('hello', 10, 4);

        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml, 'SVG must parse as XML');
        $this->assertSame('0 0 29 29', (string) $xml['viewBox']); // 21 modules + 2*4
        $this->assertSame('290', (string) $xml['width']);
        $this->assertStringNotContainsString('M3,', $svg); // nothing drawn inside the quiet zone
    }
}
