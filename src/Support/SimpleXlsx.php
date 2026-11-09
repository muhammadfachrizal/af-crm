<?php

namespace VentureDrake\LaravelCrm\Support;

use ZipArchive;

/**
 * Penulis .xlsx sederhana tanpa paket tambahan (butuh ekstensi PHP zip).
 * Tiap sheet: ['name' => string, 'columns' => [['label' => string, 'type' => text|money|int|percent]], 'rows' => [[...]]]
 */
class SimpleXlsx
{
    protected static function esc($v): string
    {
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v);

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    protected static function sheetName(string $name, array $used): string
    {
        $name = trim(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name)) ?: 'Sheet';
        $name = mb_substr($name, 0, 28);
        $base = $name;
        $n = 2;

        while (in_array($name, $used, true)) {
            $name = mb_substr($base, 0, 25).' '.$n++;
        }

        return $name;
    }

    public static function sheetXml(array $sheet): string
    {
        $columns = $sheet['columns'];
        $rows = $sheet['rows'];

        // lebar kolom perkiraan
        $widths = [];
        foreach ($columns as $i => $c) {
            $w = mb_strlen($c['label']);
            foreach (array_slice($rows, 0, 200) as $r) {
                $w = max($w, mb_strlen((string) ($r[$i] ?? '')));
            }
            $widths[$i] = min(60, max(10, $w + 2));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>';
        foreach ($widths as $i => $w) {
            $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        $xml .= '<row r="1">';
        foreach ($columns as $i => $c) {
            $xml .= '<c r="'.static::col($i).'1" s="1" t="inlineStr"><is><t>'.static::esc($c['label']).'</t></is></c>';
        }
        $xml .= '</row>';

        foreach ($rows as $ri => $row) {
            $r = $ri + 2;
            $xml .= '<row r="'.$r.'">';

            foreach ($columns as $i => $c) {
                $v = $row[$i] ?? null;
                $ref = static::col($i).$r;

                if ($v === null || $v === '') {
                    continue;
                }

                $type = $c['type'] ?? 'text';

                if (in_array($type, ['money', 'int', 'percent'], true) && is_numeric($v)) {
                    $style = $type === 'money' ? 2 : ($type === 'percent' ? 3 : 0);
                    $xml .= '<c r="'.$ref.'" s="'.$style.'"><v>'.(0 + $v).'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.static::esc($v).'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    protected static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE6F4F2"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs></styleSheet>';
    }

    /** Buat file .xlsx dan kembalikan path sementara-nya. */
    public static function build(array $sheets): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $names = [];
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $name = static::sheetName($sheet['name'] ?? 'Sheet'.$n, $names);
            $names[] = $name;

            $zip->addFromString("xl/worksheets/sheet{$n}.xml", static::sheetXml($sheet));
            $ct .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wb .= '<sheet name="'.static::esc($name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }

        $count = count($sheets);
        $rels .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

        $zip->addFromString('[Content_Types].xml', $ct.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $wb.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/styles.xml', static::styles());
        $zip->close();

        return $tmp;
    }
}
