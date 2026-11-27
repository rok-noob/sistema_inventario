<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DAO\AlmacenDAO;
use App\DAO\MovimientoDAO;
use App\Helpers\ResponseHelper;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class ReporteController
{
    public function index(): void
    {
        $almacenDao = new AlmacenDAO();
        $almacenes  = $almacenDao->listarActivos();

        $usuario  = $_SESSION['usuario'];
        $permisos = $_SESSION['permisos'] ?? [];

        require __DIR__ . '/../Views/reportes/index.php';
    }

    public function previsualizar(): void
    {
        try {
            $filtros       = $this->extraerFiltros();
            $movimientoDao = new MovimientoDAO();
            $registros     = $movimientoDao->listarParaReporte(
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                $filtros['tipo'],
                $filtros['almacen_id']
            );

            $resumen = $this->calcularResumen($registros);

            ResponseHelper::success('Reporte generado correctamente.', [
                'registros' => $registros,
                'resumen'   => $resumen,
            ]);
        } catch (Throwable $e) {
            ResponseHelper::error('Error al consultar reporte: ' . $e->getMessage(), 400);
        }
    }

    public function exportarCsv(): void
    {
        try {
            $filtros       = $this->extraerFiltros();
            $movimientoDao = new MovimientoDAO();
            $registros     = $movimientoDao->listarParaReporte(
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                $filtros['tipo'],
                $filtros['almacen_id']
            );

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $nombreArchivo = 'reporte_movimientos_' . date('Ymd_His') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                throw new \RuntimeException('No se pudo abrir el flujo de salida para CSV.');
            }

            // BOM UTF-8 para que Excel en Windows respete acentos y eñes
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'Folio',
                'Fecha',
                'Tipo',
                'Almacen Origen',
                'Almacen Destino',
                'Productos',
                'Partidas',
                'Unidades Totales',
                'Monto Total',
                'Usuario',
                'Observaciones',
            ]);

            foreach ($registros as $row) {
                fputcsv($salida, [
                    '#' . $row['id'],
                    (string) $row['fecha'],
                    (string) $row['tipo'],
                    (string) ($row['almacen_origen_nombre'] ?? 'N/A'),
                    (string) ($row['almacen_destino_nombre'] ?? 'N/A'),
                    (string) ($row['productos_resumen'] ?? ''),
                    (int) $row['total_partidas'],
                    (int) $row['total_unidades'],
                    number_format((float) $row['monto_total'], 2, '.', ''),
                    (string) $row['usuario_nombre'],
                    (string) ($row['observaciones'] ?? ''),
                ]);
            }

            fclose($salida);
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error al exportar CSV: ' . $e->getMessage();
        }
    }

    public function exportarExcel(): void
    {
        try {
            $filtros       = $this->extraerFiltros();
            $movimientoDao = new MovimientoDAO();
            $registros     = $movimientoDao->listarParaReporte(
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                $filtros['tipo'],
                $filtros['almacen_id']
            );
            $resumen = $this->calcularResumen($registros);

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Movimientos');

            // Título y subtítulo institucional
            $sheet->setCellValue('A1', 'REPORTE DE MOVIMIENTOS OPERATIVOS DE INVENTARIO');
            $sheet->mergeCells('A1:K1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $textoFiltros = $this->formatearDescripcionFiltros($filtros);
            $sheet->setCellValue('A2', 'Generado: ' . date('Y-m-d H:i:s') . ' | ' . $textoFiltros);
            $sheet->mergeCells('A2:K2');
            $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

            // Encabezados en fila 4
            $encabezados = [
                'A4' => 'Folio',
                'B4' => 'Fecha',
                'C4' => 'Tipo',
                'D4' => 'Almacén Origen',
                'E4' => 'Almacén Destino',
                'F4' => 'Productos',
                'G4' => 'Partidas',
                'H4' => 'Unidades',
                'I4' => 'Monto Total',
                'J4' => 'Registró',
                'K4' => 'Observaciones',
            ];

            foreach ($encabezados as $celda => $titulo) {
                $sheet->setCellValue($celda, $titulo);
            }

            $sheet->getStyle('A4:K4')->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '212529'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ]);
            $sheet->getRowDimension(4)->setRowHeight(24);

            $fila = 5;
            foreach ($registros as $row) {
                $sheet->setCellValue("A{$fila}", '#' . $row['id']);
                $sheet->setCellValue("B{$fila}", (string) $row['fecha']);
                $sheet->setCellValue("C{$fila}", (string) $row['tipo']);
                $sheet->setCellValue("D{$fila}", (string) ($row['almacen_origen_nombre'] ?? '—'));
                $sheet->setCellValue("E{$fila}", (string) ($row['almacen_destino_nombre'] ?? '—'));
                $sheet->setCellValue("F{$fila}", (string) ($row['productos_resumen'] ?? ''));
                $sheet->setCellValue("G{$fila}", (int) $row['total_partidas']);
                $sheet->setCellValue("H{$fila}", (int) $row['total_unidades']);
                $sheet->setCellValue("I{$fila}", (float) $row['monto_total']);
                $sheet->setCellValue("J{$fila}", (string) $row['usuario_nombre']);
                $sheet->setCellValue("K{$fila}", (string) ($row['observaciones'] ?? ''));

                $sheet->getStyle("I{$fila}")
                    ->getNumberFormat()
                    ->setFormatCode('$#,##0.00');

                $fila++;
            }

            // Fila de totales
            if (!empty($registros)) {
                $ultimaFilaDatos = $fila - 1;
                $sheet->setCellValue("A{$fila}", 'TOTALES (' . $resumen['total_movimientos'] . ' movs.)');
                $sheet->mergeCells("A{$fila}:F{$fila}");
                $sheet->setCellValue("G{$fila}", "=SUM(G5:G{$ultimaFilaDatos})");
                $sheet->setCellValue("H{$fila}", "=SUM(H5:H{$ultimaFilaDatos})");
                $sheet->setCellValue("I{$fila}", "=SUM(I5:I{$ultimaFilaDatos})");

                $sheet->getStyle("A{$fila}:K{$fila}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E9ECEF'],
                    ],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
                $sheet->getStyle("I{$fila}")
                    ->getNumberFormat()
                    ->setFormatCode('$#,##0.00');
            }

            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $nombreArchivo = 'reporte_movimientos_' . date('Ymd_His') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error al exportar Excel: ' . $e->getMessage();
        }
    }

    public function exportarPdf(): void
    {
        try {
            $filtros       = $this->extraerFiltros();
            $movimientoDao = new MovimientoDAO();
            $registros     = $movimientoDao->listarParaReporte(
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                $filtros['tipo'],
                $filtros['almacen_id']
            );
            $resumen         = $this->calcularResumen($registros);
            $textoFiltros    = htmlspecialchars($this->formatearDescripcionFiltros($filtros), ENT_QUOTES, 'UTF-8');
            $usuarioNombre   = htmlspecialchars((string) ($_SESSION['usuario']['nombre'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8');
            $fechaGeneracion = date('Y-m-d H:i:s');

            $filasHtml = '';
            if (empty($registros)) {
                $filasHtml = '<tr><td colspan="10" style="text-align:center; padding: 14px; color: #666;">No se encontraron movimientos con los filtros seleccionados.</td></tr>';
            } else {
                foreach ($registros as $row) {
                    $folio       = '#' . (int) $row['id'];
                    $fecha       = htmlspecialchars((string) $row['fecha'], ENT_QUOTES, 'UTF-8');
                    $tipo        = htmlspecialchars((string) $row['tipo'], ENT_QUOTES, 'UTF-8');
                    $origen      = htmlspecialchars((string) ($row['almacen_origen_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8');
                    $destino     = htmlspecialchars((string) ($row['almacen_destino_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8');
                    $productos   = htmlspecialchars((string) ($row['productos_resumen'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $unidades    = (int) $row['total_unidades'];
                    $monto       = '$' . number_format((float) $row['monto_total'], 2);
                    $registrador = htmlspecialchars((string) $row['usuario_nombre'], ENT_QUOTES, 'UTF-8');
                    $obs         = htmlspecialchars((string) ($row['observaciones'] ?? '—'), ENT_QUOTES, 'UTF-8');

                    $colorTipo = match ($row['tipo']) {
                        'ENTRADA'       => '#198754',
                        'SALIDA'        => '#dc3545',
                        'TRANSFERENCIA' => '#0d6efd',
                        default         => '#212529',
                    };

                    $filasHtml .= "
                        <tr>
                            <td style=\"font-weight:bold;\">{$folio}</td>
                            <td>{$fecha}</td>
                            <td style=\"font-weight:bold; color:{$colorTipo};\">{$tipo}</td>
                            <td>{$origen}</td>
                            <td>{$destino}</td>
                            <td>{$productos}</td>
                            <td style=\"text-align:center;\">{$unidades}</td>
                            <td style=\"text-align:right;\">{$monto}</td>
                            <td>{$registrador}</td>
                            <td>{$obs}</td>
                        </tr>
                    ";
                }
            }

            $totalUnidadesFmt = number_format($resumen['total_unidades']);
            $montoTotalFmt    = '$' . number_format($resumen['monto_total'], 2);

            $html = <<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #212529; }
                    .header { border-bottom: 2px solid #212529; padding-bottom: 8px; margin-bottom: 10px; }
                    .header h1 { margin: 0 0 4px 0; font-size: 15px; }
                    .meta { font-size: 8.5px; color: #555; }
                    .kpis { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
                    .kpis td { background: #f8f9fa; border: 1px solid #dee2e6; padding: 6px; text-align: center; }
                    .kpis .num { font-size: 12px; font-weight: bold; display: block; }
                    table.datos { width: 100%; border-collapse: collapse; }
                    table.datos th { background: #212529; color: #fff; padding: 6px 4px; text-align: left; font-size: 8.5px; }
                    table.datos td { border-bottom: 1px solid #dee2e6; padding: 5px 4px; vertical-align: top; }
                    table.datos tr:nth-child(even) td { background: #fcfcfd; }
                    .totales td { background: #e9ecef !important; font-weight: bold; border-top: 1.5px solid #212529; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Inventario Pro — Reporte de Movimientos Operativos</h1>
                    <div class="meta">
                        <strong>Filtros:</strong> {$textoFiltros} &nbsp;|&nbsp;
                        <strong>Generado por:</strong> {$usuarioNombre} &nbsp;|&nbsp;
                        <strong>Fecha:</strong> {$fechaGeneracion}
                    </div>
                </div>

                <table class="kpis">
                    <tr>
                        <td><span class="num">{$resumen['total_movimientos']}</span>Movimientos</td>
                        <td><span class="num" style="color:#198754;">{$resumen['entradas']}</span>Entradas</td>
                        <td><span class="num" style="color:#dc3545;">{$resumen['salidas']}</span>Salidas</td>
                        <td><span class="num" style="color:#0d6efd;">{$resumen['transferencias']}</span>Transferencias</td>
                        <td><span class="num">{$totalUnidadesFmt}</span>Unidades Movidas</td>
                        <td><span class="num">{$montoTotalFmt}</span>Valor Total</td>
                    </tr>
                </table>

                <table class="datos">
                    <thead>
                        <tr>
                            <th style="width:5%;">Folio</th>
                            <th style="width:12%;">Fecha</th>
                            <th style="width:10%;">Tipo</th>
                            <th style="width:11%;">Origen</th>
                            <th style="width:11%;">Destino</th>
                            <th style="width:19%;">Productos</th>
                            <th style="width:6%; text-align:center;">Unid.</th>
                            <th style="width:9%; text-align:right;">Monto</th>
                            <th style="width:8%;">Usuario</th>
                            <th style="width:9%;">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$filasHtml}
                    </tbody>
                    <tfoot>
                        <tr class="totales">
                            <td colspan="6" style="text-align:right;">TOTALES GENERALES:</td>
                            <td style="text-align:center;">{$totalUnidadesFmt}</td>
                            <td style="text-align:right;">{$montoTotalFmt}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </body>
            </html>
            HTML;

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $nombreArchivo = 'reporte_movimientos_' . date('Ymd_His') . '.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => true]);
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error al exportar PDF: ' . $e->getMessage();
        }
    }

    /**
     * @return array{fecha_inicio: ?string, fecha_fin: ?string, tipo: ?string, almacen_id: ?int}
     */
    private function extraerFiltros(): array
    {
        $fechaInicio = trim((string) ($_GET['fecha_inicio'] ?? ''));
        $fechaFin    = trim((string) ($_GET['fecha_fin'] ?? ''));
        $tipo        = strtoupper(trim((string) ($_GET['tipo'] ?? '')));
        $almacenId   = (int) ($_GET['almacen_id'] ?? 0);

        $fechaInicioValida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) ? $fechaInicio : null;
        $fechaFinValida    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin) ? $fechaFin : null;
        $tipoValido        = in_array($tipo, ['ENTRADA', 'SALIDA', 'TRANSFERENCIA'], true) ? $tipo : null;
        $almacenValido     = $almacenId > 0 ? $almacenId : null;

        return [
            'fecha_inicio' => $fechaInicioValida,
            'fecha_fin'    => $fechaFinValida,
            'tipo'         => $tipoValido,
            'almacen_id'   => $almacenValido,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $registros
     * @return array{total_movimientos: int, entradas: int, salidas: int, transferencias: int, total_unidades: int, monto_total: float}
     */
    private function calcularResumen(array $registros): array
    {
        $resumen = [
            'total_movimientos' => count($registros),
            'entradas'          => 0,
            'salidas'           => 0,
            'transferencias'    => 0,
            'total_unidades'    => 0,
            'monto_total'       => 0.0,
        ];

        foreach ($registros as $row) {
            match ($row['tipo']) {
                'ENTRADA'       => $resumen['entradas']++,
                'SALIDA'        => $resumen['salidas']++,
                'TRANSFERENCIA' => $resumen['transferencias']++,
                default         => null,
            };
            $resumen['total_unidades'] += (int) $row['total_unidades'];
            $resumen['monto_total']    += (float) $row['monto_total'];
        }

        return $resumen;
    }

    /**
     * @param array{fecha_inicio: ?string, fecha_fin: ?string, tipo: ?string, almacen_id: ?int} $filtros
     */
    private function formatearDescripcionFiltros(array $filtros): string
    {
        $partes = [];

        if ($filtros['fecha_inicio'] !== null || $filtros['fecha_fin'] !== null) {
            $desde    = $filtros['fecha_inicio'] ?? 'Inicio';
            $hasta    = $filtros['fecha_fin'] ?? 'Hoy';
            $partes[] = "Rango: {$desde} a {$hasta}";
        } else {
            $partes[] = 'Rango: Histórico completo';
        }

        $partes[] = 'Tipo: ' . ($filtros['tipo'] ?? 'Todos');
        $partes[] = 'Almacén ID: ' . ($filtros['almacen_id'] !== null ? '#' . $filtros['almacen_id'] : 'Todos');

        return implode(' | ', $partes);
    }
}