<?php

namespace App\Support;

/**
 * Ghi file .xlsx đơn giản (một hoặc nhiều sheet, dòng đầu in đậm) mà không
 * cần thêm dependency. PhpSpreadsheet đòi ext-gd vốn không có sẵn ở mọi
 * môi trường, trong khi xuất bảng điểm chỉ cần văn bản và số.
 *
 * Ô số (int/float) ghi dạng numeric để Excel tính toán được, còn lại ghi
 * dạng shared string UTF-8. Độ rộng cột truyền sẵn, khỏi đo chữ.
 */
class SimpleXlsx
{
    /** @var array<int, array{name: string, headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>, widths: array<int, float>}> */
    protected array $sheets = [];

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, int|float|string|null>>  $rows
     * @param  array<int, float>  $widths
     */
    public function addSheet(string $name, array $headers, array $rows, array $widths = []): static
    {
        $this->sheets[] = [
            'name' => mb_substr($name, 0, 31),
            'headers' => array_values($headers),
            'rows' => array_values(array_map('array_values', $rows)),
            'widths' => $widths,
        ];

        return $this;
    }

    public function build(): string
    {
        $strings = [];
        $stringIndex = [];

        $register = function (string $value) use (&$strings, &$stringIndex): int {
            if (! isset($stringIndex[$value])) {
                $stringIndex[$value] = count($strings);
                $strings[] = $value;
            }

            return $stringIndex[$value];
        };

        $sheetsXml = '';
        $sheetId = 0;

        foreach ($this->sheets as $sheet) {
            $sheetId++;
            $sheetsXml .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
        }

        $files = [
            '[Content_Types].xml' => $this->contentTypes(count($this->sheets)),
            '_rels/.rels' => $this->rootRels(),
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets>'.$sheetsXml.'</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(count($this->sheets)),
            'xl/styles.xml' => $this->styles(),
        ];

        // sharedStrings dựng sau khi mọi ô đã đăng ký chuỗi (lúc vẽ sheet).
        $sheetFiles = [];

        foreach ($this->sheets as $index => $sheet) {
            $sheetFiles[$index] = $this->worksheet($sheet, $register);
        }

        $files['xl/sharedStrings.xml'] = $this->sharedStrings($strings);

        foreach ($sheetFiles as $index => $xml) {
            $files['xl/worksheets/sheet'.($index + 1).'.xml'] = $xml;
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new \RuntimeException('Không tạo được tệp tạm cho Excel.');
        }

        $zip = new \ZipArchive;

        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Không nén được tệp Excel.');
        }

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        $binary = file_get_contents($path);
        @unlink($path);

        if (! is_string($binary)) {
            throw new \RuntimeException('Không đọc được tệp Excel vừa tạo.');
        }

        return $binary;
    }

    protected function contentTypes(int $sheetCount): string
    {
        $overrides = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>';
    }

    protected function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected function workbookRels(int $sheetCount): string
    {
        $rels = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId'.($sheetCount + 2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'</cellXfs></styleSheet>';
    }

    /**
     * @param  array<int, string>  $strings
     */
    protected function sharedStrings(array $strings): string
    {
        $items = '';

        foreach ($strings as $value) {
            $items .= '<si><t xml:space="preserve">'.$this->escape($value).'</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.$items.'</sst>';
    }

    /**
     * @param  array{name: string, headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>, widths: array<int, float>}  $sheet
     */
    protected function worksheet(array $sheet, callable $register): string
    {
        $columnCount = max(count($sheet['headers']), ...array_map('count', $sheet['rows'] ?: [[]]));

        $cols = '';

        for ($i = 0; $i < $columnCount; $i++) {
            $width = $sheet['widths'][$i] ?? 18;
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $rowsXml = $this->rowXml($sheet['headers'], 1, $register, true);

        foreach ($sheet['rows'] as $index => $row) {
            $rowsXml .= $this->rowXml($row, $index + 2, $register, false);
        }

        $dimension = 'A1:'.$this->columnLetter(max(0, $columnCount - 1)).(count($sheet['rows']) + 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="'.$dimension.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.$rowsXml.'</sheetData></worksheet>';
    }

    /**
     * @param  array<int, int|float|string|null>  $cells
     */
    protected function rowXml(array $cells, int $rowNumber, callable $register, bool $header): string
    {
        $xml = '<row r="'.$rowNumber.'">';

        foreach (array_values($cells) as $index => $value) {
            $cell = $this->columnLetter($index).$rowNumber;

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$cell.'"><v>'.$value.'</v></c>';
            } else {
                $text = $value === null ? '' : (string) $value;
                $xml .= '<c r="'.$cell.'" t="s"'.($header ? ' s="1"' : '').'><v>'.$register($text).'</v></c>';
            }
        }

        return $xml.'</row>';
    }

    protected function columnLetter(int $index): string
    {
        $letter = '';

        do {
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letter;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
