<?php

namespace App\Services\Export;

use ZipArchive;

/**
 * Ekspor hasil AI Tools teks ke Word (.docx) & Excel (.xlsx) — konsep repo Wordcraft/Gridcraft (Bab 53),
 * ditulis ulang minimal dengan ZipArchive (OOXML murni, tanpa dependensi). Sel diawali = + - @ diberi apostrof
 * (cegah formula injection, sama seperti ekspor CSV).
 */
class Ooxml
{
    /** @param array<int, array{type:'h1'|'h2'|'h3'|'p', text:string}> $blocks */
    public static function docx(array $blocks, string $title): string
    {
        $body = '';
        foreach ($blocks as $b) {
            [$size, $bold, $space] = match ($b['type']) {
                'h1' => [36, true, 240], 'h2' => [30, true, 200], 'h3' => [24, true, 160], default => [22, false, 80],
            };
            foreach (preg_split('/\r?\n/', $b['text']) as $line) {
                // Urutan elemen pPr mengikuti skema OOXML: keepNext sebelum spacing
                $body .= '<w:p><w:pPr>'.($b['type'] !== 'p' ? '<w:keepNext/>' : '').'<w:spacing w:before="'.($b['type'] === 'p' ? 0 : $space).'" w:after="'.$space.'"/></w:pPr>'
                    .'<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'.($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/></w:rPr>'
                    .'<w:t xml:space="preserve">'.self::x($line).'</w:t></w:r></w:p>';
            }
        }
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'docProps/core.xml' => self::core($title),
            'word/document.xml' => $doc,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    /** @param string[] $head @param array<int, array<int, string>> $rows */
    public static function xlsx(array $head, array $rows, string $sheet, string $title): string
    {
        $all = array_merge([$head], $rows);
        $xml = '';
        foreach ($all as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $v) {
                $v = (string) $v;
                if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
                    $v = "'".$v;
                }
                $xml .= '<c r="'.self::col($c).($r + 1).'" t="inlineStr"'.($r === 0 ? ' s="1"' : ' s="2"').'><is><t xml:space="preserve">'.self::x($v).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $cols = '';
        foreach (array_keys($head) as $c) {
            $max = max(array_map(fn ($row) => min(60, mb_strlen((string) (array_values($row)[$c] ?? ''))), $all));
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.max(10, $max + 2).'" customWidth="1"/>';
        }
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'.$cols.'</cols><sheetData>'.$xml.'</sheetData></worksheet>';

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'docProps/core.xml' => self::core($title),
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::x(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheet), 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE0E7FF"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ]);
    }

    private static function core(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.self::x($title).'</dc:title><dc:creator>Krafio</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>';
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function x(string $s): string
    {
        // Buang karakter kontrol yang tidak sah di XML
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function zip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'krx');
        $z = new ZipArchive;
        $z->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $z->addFromString($name, $content);
        }
        $z->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
