<?php

namespace Gponty\EntityToGoogleSheetsBundle\Service;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\ValueRange;

class GoogleSheetsExporter
{
    private Sheets $service;

    public function __construct(
        private readonly string $spreadsheetId,
        private readonly string $credentialsPath
    ) {
        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Sheets::SPREADSHEETS);
        $this->service = new Sheets($client);
    }

    public function export(array $entities): void
    {
        $this->clearAllSheets();
        $existingSheets = $this->getExistingSheets();

        $this->ensureSheetsExist($entities, $existingSheets);

        $existingSheets = $this->getExistingSheets();

        $this->fillIndexSheet($entities, $existingSheets);

        foreach ($entities as $className => $data) {
            $sheetName = $this->sanitizeSheetName($data['tableName']);
            $this->fillEntitySheet($sheetName, $className, $data['fields']);
        }
    }

    private function getExistingSheets(): array
    {
        $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
        $sheets = [];
        foreach ($spreadsheet->getSheets() as $sheet) {
            $props = $sheet->getProperties();
            $sheets[$props->getTitle()] = $props->getSheetId();
        }
        return $sheets;
    }

    private function clearAllSheets(): void
    {
        $existingSheets = $this->getExistingSheets();
        $requests = [];

        foreach ($existingSheets as $title => $sheetId) {
            $requests[] = new Request([
                'deleteSheet' => [
                    'sheetId' => $sheetId
                ]
            ]);
        }

        // Google interdit de supprimer tous les onglets, on en garde un
        array_shift($requests);

        if (!empty($requests)) {
            $batchRequest = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);
        }

        // Renommer le dernier onglet restant en "Index"
        $existingSheets = $this->getExistingSheets();
        $remainingSheetId = array_values($existingSheets)[0];

        $batchRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => [
                new Request([
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $remainingSheetId,
                            'title'   => 'Index'
                        ],
                        'fields' => 'title'
                    ]
                ])
            ]
        ]);
        $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);
    }

    private function ensureSheetsExist(array $entities, array $existing): void
    {
        $requests = [];

        foreach ($entities as $data) {
            $title = $this->sanitizeSheetName($data['tableName']);
            if (!isset($existing[$title])) {
                $requests[] = new Request([
                    'addSheet' => [
                        'properties' => ['title' => $title]
                    ]
                ]);
            }
        }

        if (!empty($requests)) {
            $batchRequest = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);
        }
    }

    private function fillIndexSheet(array $entities, array $sheetIds): void
    {
        $values = [
            ['📋 Table', '📦 Classe PHP', '🔢 Nb champs', '📝 Description']
        ];

        foreach ($entities as $className => $data) {
            $values[] = [
                $data['tableName'],
                $className,
                count($data['fields']),
                $data['description'] ?? '',
            ];
        }

        $this->writeValues('Index', 'A1', $values);

        $requests = [];

        // Style header
        $requests[] = new Request([
            'repeatCell' => [
                'range' => [
                    'sheetId'          => $sheetIds['Index'],
                    'startRowIndex'    => 0,
                    'endRowIndex'      => 1,
                    'startColumnIndex' => 0,
                    'endColumnIndex'   => 4,
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'backgroundColor' => ['red' => 0.2, 'green' => 0.5, 'blue' => 0.8],
                        'textFormat'      => [
                            'bold'            => true,
                            'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1],
                            'fontSize'        => 12
                        ]
                    ]
                ],
                'fields' => 'userEnteredFormat(backgroundColor,textFormat)'
            ]
        ]);

        // Liens cliquables vers chaque onglet
        $rowIndex = 1;
        foreach ($entities as $data) {
            $sheetName    = $this->sanitizeSheetName($data['tableName']);
            $targetSheetId = $sheetIds[$sheetName] ?? null;

            if ($targetSheetId !== null) {
                $requests[] = new Request([
                    'updateCells' => [
                        'rows'   => [[
                            'values' => [[
                                'userEnteredValue'  => ['stringValue' => $data['tableName']],
                                'userEnteredFormat' => [
                                    'textFormat' => [
                                        'link'            => ['uri' => "#gid={$targetSheetId}"],
                                        'foregroundColor' => ['red' => 0.1, 'green' => 0.3, 'blue' => 0.9],
                                        'underline'       => true,
                                    ]
                                ]
                            ]]
                        ]],
                        'fields' => 'userEnteredValue,userEnteredFormat.textFormat',
                        'range'  => [
                            'sheetId'          => $sheetIds['Index'],
                            'startRowIndex'    => $rowIndex,
                            'endRowIndex'      => $rowIndex + 1,
                            'startColumnIndex' => 0,
                            'endColumnIndex'   => 1,
                        ]
                    ]
                ]);
            }
            $rowIndex++;
        }

        // Autosize en dernier
        $requests[] = new Request([
            'autoResizeDimensions' => [
                'dimensions' => [
                    'sheetId'    => $sheetIds['Index'],
                    'dimension'  => 'COLUMNS',
                    'startIndex' => 0,
                    'endIndex'   => 4,
                ]
            ]
        ]);

        $batchRequest = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);
        $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);
    }

    private function fillEntitySheet(string $sheetName, string $className, array $fields): void
{
    $existingSheets = $this->getExistingSheets();
    $sheetId = $existingSheets[$sheetName] ?? null;
    $indexSheetId = $existingSheets['Index'] ?? 0;

    // 1. Écrire le titre
    $this->writeValues($sheetName, 'A1', [["Entité : {$className}"]]);

    // 2. Écrire le lien retour Index en ligne 2
    $this->writeValues($sheetName, 'A2', [["⬅️ Retour à l'Index"]]);

    // 3. Écrire les données à partir de la ligne 4
    $values = [
        ['🏷️ Propriété', '📋 Colonne SQL', '🔧 Type', '❓ Nullable', '📏 Longueur', '🔑 ID', '🔒 Unique', '📝 Description']
    ];
    foreach ($fields as $field) {
        $values[] = [
            $field['name'],
            $field['column'],
            $field['type'],
            $field['nullable'],
            (string)($field['length'] ?? ''),
            $field['id'],
            $field['unique'],
            $field['description'] ?? '',
        ];
    }
    $this->writeValues($sheetName, 'A4', $values);

    if ($sheetId === null) return;

    $requests = [];

    // Merge titre ligne 1
    $requests[] = new Request([
        'mergeCells' => [
            'range'     => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1, 'startColumnIndex' => 0, 'endColumnIndex' => 8],
            'mergeType' => 'MERGE_ALL'
        ]
    ]);

    // Style titre ligne 1
    $requests[] = new Request([
        'repeatCell' => [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1, 'startColumnIndex' => 0, 'endColumnIndex' => 8],
            'cell'  => [
                'userEnteredFormat' => [
                    'backgroundColor'     => ['red' => 0.15, 'green' => 0.15, 'blue' => 0.15],
                    'textFormat'          => ['bold' => true, 'fontSize' => 14, 'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1]],
                    'horizontalAlignment' => 'CENTER'
                ]
            ],
            'fields' => 'userEnteredFormat'
        ]
    ]);

    // Merge lien retour ligne 2
    $requests[] = new Request([
        'mergeCells' => [
            'range'     => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => 2, 'startColumnIndex' => 0, 'endColumnIndex' => 8],
            'mergeType' => 'MERGE_ALL'
        ]
    ]);

    // Lien retour Index avec style
    $requests[] = new Request([
        'updateCells' => [
            'rows'   => [[
                'values' => [[
                    'userEnteredValue'  => ['stringValue' => "⬅️ Retour à l'Index"],
                    'userEnteredFormat' => [
                        'backgroundColor'     => ['red' => 0.95, 'green' => 0.95, 'blue' => 0.95],
                        'horizontalAlignment' => 'CENTER',
                        'textFormat'          => [
                            'link'            => ['uri' => "#gid={$indexSheetId}"],
                            'foregroundColor' => ['red' => 0.1, 'green' => 0.3, 'blue' => 0.9],
                            'underline'       => true,
                            'bold'            => true,
                            'fontSize'        => 11,
                        ]
                    ]
                ]]
            ]],
            'fields' => 'userEnteredValue,userEnteredFormat',
            'range'  => [
                'sheetId'          => $sheetId,
                'startRowIndex'    => 1,
                'endRowIndex'      => 2,
                'startColumnIndex' => 0,
                'endColumnIndex'   => 1,
            ]
        ]
    ]);

    // Style header champs ligne 4
    $requests[] = new Request([
        'repeatCell' => [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => 3, 'endRowIndex' => 4, 'startColumnIndex' => 0, 'endColumnIndex' => 8],
            'cell'  => [
                'userEnteredFormat' => [
                    'backgroundColor' => ['red' => 0.2, 'green' => 0.5, 'blue' => 0.8],
                    'textFormat'      => ['bold' => true, 'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1]],
                ]
            ],
            'fields' => 'userEnteredFormat(backgroundColor,textFormat)'
        ]
    ]);

    // Freeze 4 premières lignes
    $requests[] = new Request([
        'updateSheetProperties' => [
            'properties' => ['sheetId' => $sheetId, 'gridProperties' => ['frozenRowCount' => 4]],
            'fields'     => 'gridProperties.frozenRowCount'
        ]
    ]);

    // Autosize en dernier
    $requests[] = new Request([
        'autoResizeDimensions' => [
            'dimensions' => [
                'sheetId'    => $sheetId,
                'dimension'  => 'COLUMNS',
                'startIndex' => 0,
                'endIndex'   => 8,
            ]
        ]
    ]);

    $batchRequest = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);
    $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchRequest);
}

    private function writeValues(string $sheet, string $range, array $values): void
    {
        $body = new ValueRange(['values' => $values]);
        $this->service->spreadsheets_values->update(
            $this->spreadsheetId,
            "{$sheet}!{$range}",
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]*?\/\\\\]/', '_', $name);
        return substr($name, 0, 100);
    }
}