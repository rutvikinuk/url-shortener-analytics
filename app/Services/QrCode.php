<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Minimal dependency-free QR code encoder (byte mode, error correction M,
 * versions 1-10 => up to 213 bytes). Enough for short-link URLs.
 *
 * Steps: text -> data bits -> Reed-Solomon error correction -> place bits
 * in a grid around the fixed patterns -> pick the best of 8 masks -> SVG.
 */
class QrCode
{
    /** version => [ec codewords per block, [[blocks, data codewords per block], ...]] (level M) */
    private const BLOCKS = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $version;

    private int $size;

    /** @var array<int, array<int, bool>> dark modules, [row][col] */
    private array $modules = [];

    /** @var array<int, array<int, bool>> true where a cell is a fixed pattern (not data) */
    private array $isFunction = [];

    /** @var int[] */
    private static array $exp = [];

    /** @var int[] */
    private static array $log = [];

    public static function maxBytes(): int
    {
        return 213;
    }

    /** @return bool[][] matrix of dark (true) / light (false) modules, without quiet zone */
    public static function matrix(string $text): array
    {
        $qr = new self;
        $qr->build($text);

        return $qr->modules;
    }

    public static function svg(string $text, int $scale = 8, int $quietZone = 4): string
    {
        $matrix = self::matrix($text);
        $n = count($matrix);
        $total = $n + 2 * $quietZone;
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= sprintf('M%d,%dh1v1h-1z', $x + $quietZone, $y + $quietZone);
                }
            }
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges">'.
            '<rect width="%1$d" height="%1$d" fill="#fff"/><path d="%3$s" fill="#000"/></svg>',
            $total, $total * $scale, $path
        );
    }

    private function build(string $text): void
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $this->version = $this->pickVersion(count($bytes));
        $this->size = 17 + 4 * $this->version;
        self::initTables();

        $codewords = $this->interleave($this->dataCodewords($bytes));

        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->drawFunctionPatterns();
        $this->placeData($codewords);

        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormat($mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // XOR again = undo
        }
        $this->applyMask($bestMask);
        $this->drawFormat($bestMask);
    }

    private function pickVersion(int $length): int
    {
        foreach (self::BLOCKS as $version => [, $groups]) {
            $capacity = 0;
            foreach ($groups as [$blocks, $data]) {
                $capacity += $blocks * $data;
            }
            $countBits = $version <= 9 ? 8 : 16;
            if ((4 + $countBits + 8 * $length) <= 8 * $capacity) {
                return $version;
            }
        }

        throw new InvalidArgumentException('Text too long for QR code (max '.self::maxBytes().' bytes).');
    }

    /** @param int[] $bytes @return int[] */
    private function dataCodewords(array $bytes): array
    {
        [, $groups] = self::BLOCKS[$this->version];
        $capacity = 0;
        foreach ($groups as [$blocks, $data]) {
            $capacity += $blocks * $data;
        }

        $bits = [];
        $push = function (int $value, int $count) use (&$bits) {
            for ($i = $count - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $push(0b0100, 4); // byte mode
        $push(count($bytes), $this->version <= 9 ? 8 : 16);
        foreach ($bytes as $b) {
            $push($b, 8);
        }
        $push(0, min(4, $capacity * 8 - count($bits))); // terminator
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $codewords[] = bindec(implode('', $chunk));
        }
        for ($pad = 0xEC; count($codewords) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $codewords[] = $pad;
        }

        return $codewords;
    }

    /** @param int[] $data @return int[] */
    private function interleave(array $data): array
    {
        [$ecLen, $groups] = self::BLOCKS[$this->version];
        $blocks = [];
        $offset = 0;
        foreach ($groups as [$count, $len]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $offset, $len);
                $offset += $len;
                $blocks[] = ['data' => $block, 'ec' => $this->reedSolomon($block, $ecLen)];
            }
        }

        $out = [];
        $maxData = max(array_map(fn ($b) => count($b['data']), $blocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $b) {
                if (isset($b['data'][$i])) {
                    $out[] = $b['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($blocks as $b) {
                $out[] = $b['ec'][$i];
            }
        }

        return $out;
    }

    private static function initTables(): void
    {
        if (self::$exp) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** @param int[] $data @return int[] remainder of data(x) * x^degree divided by the generator polynomial */
    private function reedSolomon(array $data, int $degree): array
    {
        // generator = (x - a^0)(x - a^1)...(x - a^(degree-1)); coefficients highest power first, leading 1 implied
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= self::gfMul($coef, self::$exp[$i]);
            }
            $gen = $next;
        }

        $rem = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($rem);
            $rem[] = 0;
            foreach ($rem as $i => $_) {
                $rem[$i] ^= self::gfMul($gen[$i + 1], $factor);
            }
        }

        return $rem;
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        $n = $this->size;
        for ($i = 0; $i < $n; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
                        continue;
                    }
                    $dist = max(abs($dx), abs($dy));
                    $this->setFunction($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
        $pos = self::ALIGNMENT[$this->version];
        $last = count($pos) - 1;
        foreach ($pos as $i => $cx) {
            foreach ($pos as $j => $cy) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue; // overlaps a finder pattern
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormat(0); // reserve the format areas
        if ($this->version >= 7) {
            $rem = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($this->version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->setFunction($a, $b, $bit);
                $this->setFunction($b, $a, $bit);
            }
        }
    }

    private function drawFormat(int $mask): void
    {
        $n = $this->size;
        $data = (0b00 << 3) | $mask; // error correction level M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($n - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $n - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $n - 8, true); // always-dark module
    }

    /** @param int[] $codewords */
    private function placeData(array $codewords): void
    {
        $n = $this->size;
        $bits = [];
        foreach ($codewords as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($cw >> $i) & 1) === 1;
            }
        }
        $k = 0;
        $total = count($bits);
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) === 0);
                    $y = $upward ? $n - 1 - $vert : $vert;
                    if (! $this->isFunction[$y][$x] && $k < $total) {
                        $this->modules[$y][$x] = $bits[$k++];
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert && ! $this->isFunction[$y][$x]) {
                    $this->modules[$y][$x] = ! $this->modules[$y][$x];
                }
            }
        }
    }

    /** Standard four penalty rules: runs, 2x2 blocks, finder-like patterns, dark/light balance. */
    private function penalty(): int
    {
        $n = $this->size;
        $score = 0;
        $lines = [];
        for ($i = 0; $i < $n; $i++) {
            $lines[] = $this->modules[$i];
            $lines[] = array_column($this->modules, $i);
        }
        foreach ($lines as $line) {
            $run = 1;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n && $line[$i] === $line[$i - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }
                    $run = 1;
                }
            }
            $str = implode('', array_map(fn ($b) => $b ? '1' : '0', $line));
            $score += 40 * (substr_count('0000'.$str.'0000', '10111010000') + substr_count('0000'.$str.'0000', '00001011101'));
        }
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $dark = 0;
        foreach ($this->modules as $row) {
            $dark += count(array_filter($row));
        }
        $percent = $dark * 100 / ($n * $n);
        $score += 10 * (int) floor(abs($percent - 50) / 5);

        return $score;
    }
}
