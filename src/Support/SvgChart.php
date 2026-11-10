<?php

namespace VentureDrake\LaravelCrm\Support;

/**
 * Grafik SVG murni (tanpa JavaScript / library) - tampil di layar, ikut tercetak ke PDF.
 * Spesifikasi: ['type' => bar|hbar|line|donut, 'title', 'labels' => [], 'series' => [['name', 'values' => [], 'color'?, 'colors'?]], 'format' => money|int|percent]
 */
class SvgChart
{
    public const COLORS = ['#0f766e', '#f59e0b', '#2563eb', '#dc2626', '#64748b', '#16a34a', '#9333ea', '#0891b2'];

    public static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    /** Angka ringkas: 1,5 jt / 2,3 M / 450 rb. */
    public static function short(float $v, string $format = 'int'): string
    {
        if ($format === 'percent') {
            return rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',').'%';
        }

        $abs = abs($v);

        if ($format === 'money' || $abs >= 1000) {
            if ($abs >= 1e12) {
                return rtrim(rtrim(number_format($v / 1e12, 1, ',', '.'), '0'), ',').' T';
            }
            if ($abs >= 1e9) {
                return rtrim(rtrim(number_format($v / 1e9, 1, ',', '.'), '0'), ',').' M';
            }
            if ($abs >= 1e6) {
                return rtrim(rtrim(number_format($v / 1e6, 1, ',', '.'), '0'), ',').' jt';
            }
            if ($abs >= 1e3 && $format === 'money') {
                return rtrim(rtrim(number_format($v / 1e3, 1, ',', '.'), '0'), ',').' rb';
            }
        }

        return number_format($v, floor($v) == $v ? 0 : 1, ',', '.');
    }

    protected static function niceMax(float $max): float
    {
        if ($max <= 0) {
            return 1;
        }

        $pow = 10 ** floor(log10($max));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($max <= $m * $pow) {
                return $m * $pow;
            }
        }

        return 10 * $pow;
    }

    protected static function color(array $series, int $i, int $j = 0): string
    {
        if (! empty($series['colors'][$j])) {
            return $series['colors'][$j];
        }

        return $series['color'] ?? self::COLORS[$i % count(self::COLORS)];
    }

    protected static function legend(array $series, int $x, int $y): string
    {
        if (count($series) < 2) {
            return '';
        }

        $out = '';
        foreach ($series as $i => $s) {
            $out .= '<rect x="'.$x.'" y="'.($y + $i * 16 - 9).'" width="10" height="10" rx="2" fill="'.self::color($s, $i).'"/>'
                .'<text x="'.($x + 15).'" y="'.($y + $i * 16).'" font-size="11" fill="#475569">'.self::e($s['name']).'</text>';
        }

        return $out;
    }

    public static function render(array $c): string
    {
        $labels = $c['labels'] ?? [];
        $series = $c['series'] ?? [];

        if (empty($labels) || empty($series) || collect($series)->flatMap(fn ($s) => $s['values'])->filter()->isEmpty()) {
            return '<div class="text-sm opacity-60 py-6 text-center">Belum ada data untuk grafik ini.</div>';
        }

        return match ($c['type'] ?? 'bar') {
            'hbar' => static::hbar($c),
            'line' => static::line($c),
            'donut' => static::donut($c),
            default => static::bar($c),
        };
    }

    protected static function bar(array $c): string
    {
        $labels = $c['labels'];
        $series = $c['series'];
        $fmt = $c['format'] ?? 'int';
        $W = 720;
        $H = 300;
        $L = 56;
        $R = 16;
        $T = 24;
        $B = 56;
        $plotW = $W - $L - $R;
        $plotH = $H - $T - $B;
        $max = static::niceMax((float) collect($series)->flatMap(fn ($s) => $s['values'])->max());

        $svg = '<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" role="img" xmlns="http://www.w3.org/2000/svg" font-family="sans-serif">';

        for ($g = 0; $g <= 4; $g++) {
            $y = $T + $plotH - $plotH * $g / 4;
            $svg .= '<line x1="'.$L.'" y1="'.$y.'" x2="'.($W - $R).'" y2="'.$y.'" stroke="#e2e8f0"/>'
                .'<text x="'.($L - 6).'" y="'.($y + 4).'" font-size="10" text-anchor="end" fill="#64748b">'.self::e(static::short($max * $g / 4, $fmt)).'</text>';
        }

        $n = count($labels);
        $groupW = $plotW / $n;
        $ns = count($series);
        $barW = min(46, ($groupW * 0.72) / $ns);

        foreach ($labels as $j => $label) {
            $gx = $L + $groupW * $j + ($groupW - $barW * $ns) / 2;

            foreach ($series as $i => $s) {
                $v = (float) ($s['values'][$j] ?? 0);
                $h = $plotH * $v / $max;
                $x = $gx + $i * $barW;
                $y = $T + $plotH - $h;

                $svg .= '<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($barW - 2, 1).'" height="'.round($h, 1).'" rx="3" fill="'.self::color($s, $i, $j).'"><title>'
                    .self::e($s['name'].' - '.$label.': '.number_format($v, 0, ',', '.')).'</title></rect>';

                if ($v > 0 && $n <= 8) {
                    $svg .= '<text x="'.round($x + ($barW - 2) / 2, 1).'" y="'.round($y - 4, 1).'" font-size="9.5" text-anchor="middle" fill="#334155">'.self::e(static::short($v, $fmt)).'</text>';
                }
            }

            $short = mb_strlen($label) > 14 ? mb_substr($label, 0, 13).'…' : $label;
            $svg .= '<text x="'.round($L + $groupW * ($j + 0.5), 1).'" y="'.($T + $plotH + 16).'" font-size="10.5" text-anchor="middle" fill="#475569"><title>'.self::e($label).'</title>'.self::e($short).'</text>';
        }

        return $svg.static::legend($series, $L + 8, 12).'</svg>';
    }

    protected static function hbar(array $c): string
    {
        $labels = $c['labels'];
        $series = $c['series'];
        $fmt = $c['format'] ?? 'int';
        $n = count($labels);
        $ns = count($series);
        $rowH = max(22, 14 * $ns + 10);
        $W = 720;
        $L = 170;
        $R = 70;
        $T = 20;
        $H = $T + $n * $rowH + 12;
        $plotW = $W - $L - $R;
        $max = static::niceMax((float) collect($series)->flatMap(fn ($s) => $s['values'])->max());

        $svg = '<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" role="img" xmlns="http://www.w3.org/2000/svg" font-family="sans-serif">';

        foreach ($labels as $j => $label) {
            $y0 = $T + $j * $rowH;
            $short = mb_strlen($label) > 26 ? mb_substr($label, 0, 25).'…' : $label;
            $svg .= '<text x="'.($L - 8).'" y="'.($y0 + $rowH / 2 + 3).'" font-size="11" text-anchor="end" fill="#475569"><title>'.self::e($label).'</title>'.self::e($short).'</text>';

            foreach ($series as $i => $s) {
                $v = (float) ($s['values'][$j] ?? 0);
                $w = $plotW * $v / $max;
                $bh = 12;
                $y = $y0 + 3 + $i * ($bh + 2);

                $svg .= '<rect x="'.$L.'" y="'.$y.'" width="'.round(max($w, $v > 0 ? 2 : 0), 1).'" height="'.$bh.'" rx="3" fill="'.self::color($s, $i, $j).'"><title>'
                    .self::e($s['name'].' - '.$label.': '.number_format($v, 0, ',', '.')).'</title></rect>'
                    .'<text x="'.round($L + $w + 6, 1).'" y="'.($y + 10).'" font-size="10" fill="#334155">'.self::e(static::short($v, $fmt)).'</text>';
            }
        }

        return $svg.static::legend($series, $W - 150, 12).'</svg>';
    }

    protected static function line(array $c): string
    {
        $labels = $c['labels'];
        $series = $c['series'];
        $fmt = $c['format'] ?? 'int';
        $W = 720;
        $H = 280;
        $L = 56;
        $R = 20;
        $T = 24;
        $B = 40;
        $plotW = $W - $L - $R;
        $plotH = $H - $T - $B;
        $max = static::niceMax((float) collect($series)->flatMap(fn ($s) => $s['values'])->max());
        $n = count($labels);

        $svg = '<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" role="img" xmlns="http://www.w3.org/2000/svg" font-family="sans-serif">';

        for ($g = 0; $g <= 4; $g++) {
            $y = $T + $plotH - $plotH * $g / 4;
            $svg .= '<line x1="'.$L.'" y1="'.$y.'" x2="'.($W - $R).'" y2="'.$y.'" stroke="#e2e8f0"/>'
                .'<text x="'.($L - 6).'" y="'.($y + 4).'" font-size="10" text-anchor="end" fill="#64748b">'.self::e(static::short($max * $g / 4, $fmt)).'</text>';
        }

        $xAt = fn ($j) => $n > 1 ? $L + $plotW * $j / ($n - 1) : $L + $plotW / 2;

        foreach ($series as $i => $s) {
            $col = self::color($s, $i);
            $pts = [];
            foreach ($labels as $j => $_) {
                $pts[] = round($xAt($j), 1).','.round($T + $plotH - $plotH * ((float) ($s['values'][$j] ?? 0)) / $max, 1);
            }

            $svg .= '<polyline points="'.implode(' ', $pts).'" fill="none" stroke="'.$col.'" stroke-width="2.5" stroke-linejoin="round"/>';

            foreach ($labels as $j => $label) {
                [$px, $py] = explode(',', $pts[$j]);
                $svg .= '<circle cx="'.$px.'" cy="'.$py.'" r="3.5" fill="'.$col.'"><title>'.self::e($s['name'].' - '.$label.': '.number_format((float) ($s['values'][$j] ?? 0), 0, ',', '.')).'</title></circle>';
            }
        }

        foreach ($labels as $j => $label) {
            $svg .= '<text x="'.round($xAt($j), 1).'" y="'.($T + $plotH + 18).'" font-size="10.5" text-anchor="middle" fill="#475569">'.self::e($label).'</text>';
        }

        return $svg.static::legend($series, $L + 8, 12).'</svg>';
    }

    protected static function donut(array $c): string
    {
        $labels = $c['labels'];
        $values = array_map('floatval', $c['series'][0]['values']);
        $total = array_sum($values);
        $W = 720;
        $cx = 130;
        $cy = 130;
        $r = 100;
        $inner = 58;
        $H = max(260, 40 + count($labels) * 22);

        $svg = '<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" role="img" xmlns="http://www.w3.org/2000/svg" font-family="sans-serif">';
        $angle = -M_PI / 2;

        foreach ($values as $i => $v) {
            if ($v <= 0) {
                continue;
            }

            $frac = $v / $total;
            $col = self::COLORS[$i % count(self::COLORS)];

            if ($frac >= 0.9999) {
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.(($r + $inner) / 2).'" fill="none" stroke="'.$col.'" stroke-width="'.($r - $inner).'"/>';
            } else {
                $a2 = $angle + $frac * 2 * M_PI;
                $large = $frac > 0.5 ? 1 : 0;
                $p = fn ($rad, $a) => round($cx + $rad * cos($a), 1).' '.round($cy + $rad * sin($a), 1);

                $svg .= '<path d="M '.$p($r, $angle).' A '.$r.' '.$r.' 0 '.$large.' 1 '.$p($r, $a2)
                    .' L '.$p($inner, $a2).' A '.$inner.' '.$inner.' 0 '.$large.' 0 '.$p($inner, $angle).' Z" fill="'.$col.'"><title>'
                    .self::e($labels[$i].': '.number_format($v, 0, ',', '.')).'</title></path>';
                $angle = $a2;
            }
        }

        $svg .= '<text x="'.$cx.'" y="'.($cy - 2).'" font-size="20" font-weight="bold" text-anchor="middle" fill="#0f172a">'.self::e(number_format($total, 0, ',', '.')).'</text>'
            .'<text x="'.$cx.'" y="'.($cy + 16).'" font-size="11" text-anchor="middle" fill="#64748b">total</text>';

        foreach ($labels as $i => $label) {
            $y = 30 + $i * 22;
            $pct = $total > 0 ? $values[$i] / $total * 100 : 0;
            $svg .= '<rect x="270" y="'.($y - 10).'" width="12" height="12" rx="3" fill="'.self::COLORS[$i % count(self::COLORS)].'"/>'
                .'<text x="290" y="'.$y.'" font-size="12" fill="#334155">'.self::e(mb_strlen($label) > 34 ? mb_substr($label, 0, 33).'…' : $label)
                .' - '.self::e(number_format($values[$i], 0, ',', '.')).' ('.self::e(number_format($pct, 1, ',', '.')).'%)</text>';
        }

        return $svg.'</svg>';
    }
}
