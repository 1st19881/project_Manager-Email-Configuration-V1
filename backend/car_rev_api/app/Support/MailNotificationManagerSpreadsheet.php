<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class MailNotificationManagerSpreadsheet
{
    /** @var list<string> */
    public const HEADERS = [
        'employee_code',
        'plant',
        'position_name',
        'department_name',
        'name_th',
        'name_en',
        'email',
        'active_flag',
    ];

    public function createTemplate(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create Excel template.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml());
        $zip->close();
    }

    /** @return list<array<string, string|null>> */
    public function read(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'csv'
            ? $this->readCsv($file->getRealPath())
            : $this->readXlsx($file->getRealPath());
    }

    /** @return list<array<string, string|null>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded CSV file.');
        }

        $rows = [];
        $headers = null;

        while (($values = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($values[0] ?? ''));
                $headers = array_map(fn ($value): string => strtolower(trim((string) $value)), $values);
                $this->assertHeaders($headers);

                continue;
            }

            $this->appendRow($rows, $headers, $values);
        }

        fclose($handle);

        if ($headers === null) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        return $rows;
    }

    /** @return list<array<string, string|null>> */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The uploaded Excel file is invalid or damaged.');
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStrings = $this->readSharedStrings($zip);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The first worksheet could not be found.');
        }

        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('The first worksheet could not be read.');
        }

        $sheet->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $matrix = [];

        foreach ($sheet->xpath('//main:sheetData/main:row') ?: [] as $row) {
            $row->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $values = [];

            foreach ($row->xpath('main:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $matches);
                $column = $this->columnNumber($matches[0] ?? 'A');
                $type = (string) $cell['t'];
                $inlineText = $cell->xpath('main:is/main:t');
                $cellValue = $cell->xpath('main:v');

                if ($type === 'inlineStr') {
                    $value = (string) ($inlineText[0] ?? '');
                } elseif ($type === 's') {
                    $value = $sharedStrings[(int) ($cellValue[0] ?? 0)] ?? '';
                } else {
                    $value = (string) ($cellValue[0] ?? '');
                }

                $values[$column] = $value;
            }

            $matrix[] = $values;
        }

        if ($matrix === []) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        $headers = array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            $this->orderedValues(array_shift($matrix)),
        );
        $this->assertHeaders($headers);
        $rows = [];

        foreach ($matrix as $values) {
            $this->appendRow($rows, $headers, $this->orderedValues($values));
        }

        return $rows;
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);

        if (! $document instanceof SimpleXMLElement) {
            return [];
        }

        $document->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];

        foreach ($document->xpath('//main:si') ?: [] as $item) {
            $item->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = '';

            foreach ($item->xpath('.//main:t') ?: [] as $textNode) {
                $text .= (string) $textNode;
            }

            $values[] = $text;
        }

        return $values;
    }

    /** @param list<array<string, string|null>> $rows
     * @param  list<string>  $headers
     * @param  array<int, mixed>  $values
     */
    private function appendRow(array &$rows, array $headers, array $values): void
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $value = trim((string) ($values[$index] ?? ''));
            $row[$header] = $value === '' ? null : $value;
        }

        if (array_filter($row, fn ($value): bool => $value !== null) !== []) {
            $rows[] = $row;
        }
    }

    /** @param list<string> $headers */
    private function assertHeaders(array $headers): void
    {
        if ($headers !== self::HEADERS) {
            throw new RuntimeException('The columns do not match the template. Please download and use the latest template.');
        }
    }

    /** @param array<int, string> $values
     * @return list<string>
     */
    private function orderedValues(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $ordered = [];

        for ($index = 0; $index <= max(array_keys($values)); $index++) {
            $ordered[] = $values[$index] ?? '';
        }

        return $ordered;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;

        foreach (str_split($letters) as $letter) {
            $number = ($number * 26) + ord($letter) - 64;
        }

        return $number - 1;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Recipients" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function worksheetXml(): string
    {
        $cells = '';

        foreach (self::HEADERS as $index => $header) {
            $column = chr(65 + $index);
            $cells .= '<c r="'.$column.'1" t="inlineStr" s="1"><is><t>'.$header.'</t></is></c>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="14" customWidth="1"/><col min="2" max="5" width="24" customWidth="1"/><col min="6" max="6" width="32" customWidth="1"/><col min="7" max="7" width="14" customWidth="1"/></cols>'
            .'<sheetData><row r="1" ht="24" customHeight="1">'.$cells.'</row></sheetData>'
            .'<autoFilter ref="A1:G1"/>'
            .'<dataValidations count="1"><dataValidation type="list" allowBlank="1" sqref="G2:G1001"><formula1>"Y,N"</formula1></dataValidation></dataValidations>'
            .'</worksheet>';
    }
}
