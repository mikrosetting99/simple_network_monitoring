<?php
declare(strict_types=1);

/** Pembaca/penulis .xlsx minimal (sheet pertama, teks saja) memakai ext-zip dan SimpleXML bawaan XAMPP. */
final class Xlsx
{
    public static function available(): bool
    {
        return class_exists('ZipArchive') && function_exists('simplexml_load_string');
    }

    /** @return array<int,array<int,string>> baris => kolom (dimulai dari indeks 0) */
    public static function read(string $path, int $maxRows = 5000): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File bukan .xlsx yang valid.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $ss = simplexml_load_string($xml);
            foreach ($ss->si ?? [] as $si) {
                // Teks bisa terpecah dalam beberapa <r> (rich text).
                $t = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r ?? [] as $r) {
                    $t .= (string) $r->t;
                }
                $shared[] = $t;
            }
        }

        $sheetPath = self::firstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Sheet pertama tidak ditemukan.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            if (count($rows) >= $maxRows + 1) {
                break;
            }
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::colIndex((string) $c['r']);
                $type = (string) $c['t'];
                if ($type === 's') {
                    $val = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string) $c->is->t;
                } else {
                    $val = (string) $c->v;
                }
                $cells[$col] = trim($val);
            }
            $line = [];
            $last = $cells ? max(array_keys($cells)) : -1;
            for ($i = 0; $i <= $last; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }
        return $rows;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $wbx = simplexml_load_string($wb);
            $ns = $wbx->getNamespaces(true);
            $first = $wbx->sheets->sheet[0] ?? null;
            if ($first !== null && isset($ns['r'])) {
                $rid = (string) $first->attributes($ns['r'])['id'];
                foreach (simplexml_load_string($rels)->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    }
                }
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', strtoupper($ref), $m);
        $n = 0;
        foreach (str_split($m[0] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** Tulis satu sheet berisi teks ke file .xlsx. */
    public static function write(string $path, array $rows, string $sheetName = 'Perangkat'): void
    {
        $data = '';
        foreach ($rows as $r => $cols) {
            $data .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($cols) as $c => $val) {
                $ref = chr(65 + $c) . ($r + 1);
                $data .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $val, ENT_XML1) . '</t></is></c>';
            }
            $data .= '</row>';
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="30" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="3" width="20" customWidth="1"/></cols><sheetData>' . $data . '</sheetData></worksheet>');
        $zip->close();
    }
}
