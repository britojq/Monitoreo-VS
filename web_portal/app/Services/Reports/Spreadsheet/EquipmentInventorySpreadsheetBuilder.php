<?php

declare(strict_types=1);

namespace App\Services\Reports\Spreadsheet;

/**
 * Constructor de documentos estructurados SpreadsheetML XML para inventario tecnológico.
 */
class EquipmentInventorySpreadsheetBuilder
{
    /**
     * Construye el documento SpreadsheetML XML con las dos hojas exactas del formato de inventario.
     */
    public function buildEquipmentInventorySpreadsheetXml(array $centerRows, array $workstationRows): string
    {
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $xml .= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
        $xml .= " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
        $xml .= " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";

        $xml .= SpreadsheetXmlStyles::getStylesXml();

        // HOJA 1: Centro de Datos
        $xml .= " <Worksheet ss:Name=\"Centro de Datos\">\n";
        $xml .= "  <Table>\n";
        $xml .= "   <Column ss:Width=\"60\"/><Column ss:Width=\"80\"/><Column ss:Width=\"110\"/><Column ss:Width=\"100\"/><Column ss:Width=\"50\"/><Column ss:Width=\"60\"/><Column ss:Width=\"60\"/><Column ss:Width=\"80\"/><Column ss:Width=\"50\"/><Column ss:Width=\"80\"/><Column ss:Width=\"90\"/><Column ss:Width=\"80\"/><Column ss:Width=\"130\"/><Column ss:Width=\"90\"/><Column ss:Width=\"110\"/><Column ss:Width=\"80\"/><Column ss:Width=\"120\"/>\n";
        $xml .= "   <Row ss:Height=\"22\"><Cell ss:StyleID=\"HeaderTitle\"><Data ss:Type=\"String\">Inventario de Equipos</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"18\"><Cell ss:StyleID=\"HeaderSubtitle\"><Data ss:Type=\"String\">Gerencia General de ATIT</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Gerencia:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">GERENCIA GENERAL DE ATIT</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Responsable Regional:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">DIVISIÓN DE OPERACIONES Y TELECOMUNICACIONES</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Estados asociados:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"24\">\n";
        $centerHeaders = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
        foreach ($centerHeaders as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . "</Data></Cell>\n";
        }
        $xml .= "   </Row>\n";
        foreach ($centerRows as $r) {
            $xml .= "   <Row ss:Height=\"18\">\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['rack']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['physical']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['virtualized']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['hardware']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['cpu']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['services']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ip']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['mac']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['admin']) . "</Data></Cell>\n";
            $xml .= "   </Row>\n";
        }
        $xml .= "  </Table>\n";
        $xml .= " </Worksheet>\n";

        // HOJA 2: Estaciones de Trabajo
        $xml .= " <Worksheet ss:Name=\"Estaciones de Trabajo\">\n";
        $xml .= "  <Table>\n";
        $xml .= "   <Column ss:Width=\"110\"/><Column ss:Width=\"70\"/><Column ss:Width=\"100\"/><Column ss:Width=\"110\"/><Column ss:Width=\"50\"/><Column ss:Width=\"60\"/><Column ss:Width=\"60\"/><Column ss:Width=\"90\"/><Column ss:Width=\"110\"/><Column ss:Width=\"130\"/><Column ss:Width=\"75\"/><Column ss:Width=\"85\"/><Column ss:Width=\"80\"/><Column ss:Width=\"110\"/><Column ss:Width=\"80\"/><Column ss:Width=\"90\"/>\n";
        $xml .= "   <Row ss:Height=\"22\"><Cell ss:StyleID=\"HeaderTitle\"><Data ss:Type=\"String\">Inventario de Equipos</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"18\"><Cell ss:StyleID=\"HeaderSubtitle\"><Data ss:Type=\"String\">Gerencia General de ATIT</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Gerencia:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">GERENCIA GENERAL DE ATIT</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Responsable Regional:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">SOPORTE TÉCNICO REGIONAL</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Estados asociados:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"20\"><Cell ss:Index=\"10\" ss:MergeAcross=\"4\" ss:StyleID=\"ColHeaderGroup\"><Data ss:Type=\"String\">ASIGNADO</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"24\">\n";
        $wsHeaders = ['TIPO DE EQUIPO', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'SISTEMA OPERATIVO', 'APLICATIVOS INSTALADOS', 'NOMBRE Y APELLIDO', 'CÉDULA', 'TELÉFONO', 'ESTADO', 'UNIDAD', 'CONDICIONES', 'OBSERVACION'];
        foreach ($wsHeaders as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . "</Data></Cell>\n";
        }
        $xml .= "   </Row>\n";
        foreach ($workstationRows as $r) {
            $xml .= "   <Row ss:Height=\"18\">\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['type']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['apps']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['custodian']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['id_number']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['phone']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['state']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['unit']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['observation']) . "</Data></Cell>\n";
            $xml .= "   </Row>\n";
        }
        $xml .= "  </Table>\n";
        $xml .= " </Worksheet>\n";
        $xml .= "</Workbook>\n";

        return $xml;
    }

    /**
     * Construye un libro Excel de una sola categoría bajo el formato Centro de Datos.
     */
    public function buildSingleCategorySpreadsheetXml(string $sheetName, string $title, string $categoryDesc, array $rows): string
    {
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $xml .= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
        $xml .= " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
        $xml .= " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";

        $xml .= SpreadsheetXmlStyles::getStylesXml();

        $xml .= ' <Worksheet ss:Name="' . $esc($sheetName) . "\">\n";
        $xml .= "  <Table>\n";
        $xml .= "   <Column ss:Width=\"60\"/><Column ss:Width=\"80\"/><Column ss:Width=\"110\"/><Column ss:Width=\"100\"/><Column ss:Width=\"50\"/><Column ss:Width=\"60\"/><Column ss:Width=\"60\"/><Column ss:Width=\"80\"/><Column ss:Width=\"50\"/><Column ss:Width=\"80\"/><Column ss:Width=\"90\"/><Column ss:Width=\"80\"/><Column ss:Width=\"130\"/><Column ss:Width=\"90\"/><Column ss:Width=\"110\"/><Column ss:Width=\"80\"/><Column ss:Width=\"120\"/>\n";
        $xml .= "   <Row ss:Height=\"22\"><Cell ss:StyleID=\"HeaderTitle\"><Data ss:Type=\"String\">Inventario de Equipos</Data></Cell></Row>\n";
        $xml .= '   <Row ss:Height="18"><Cell ss:StyleID="HeaderSubtitle"><Data ss:Type="String">' . $esc($title) . "</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Gerencia:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">GERENCIA GENERAL DE ATIT</Data></Cell></Row>\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Categoría:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">' . $esc($categoryDesc) . "</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Estados asociados:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"24\">\n";
        $headers = ['RACK', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'S.O', 'FÍSICO', 'VIRTUALIZADO', 'HARDWARE', 'PROCESADOR', 'SERVICIOS', 'IP', 'MAC', 'CONDICIONES', 'ADMINISTRADOR'];
        foreach ($headers as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . "</Data></Cell>\n";
        }
        $xml .= "   </Row>\n";
        foreach ($rows as $r) {
            $xml .= "   <Row ss:Height=\"18\">\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['rack']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['physical']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['virtualized']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['hardware']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['cpu']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['services']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ip']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['mac']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['admin']) . "</Data></Cell>\n";
            $xml .= "   </Row>\n";
        }
        $xml .= "  </Table>\n";
        $xml .= " </Worksheet>\n";
        $xml .= "</Workbook>\n";

        return $xml;
    }

    /**
     * Construye un libro Excel de una sola hoja bajo el formato Estaciones de Trabajo.
     */
    public function buildWorkstationCategorySpreadsheetXml(array $rows): string
    {
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $xml .= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
        $xml .= " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
        $xml .= " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";

        $xml .= SpreadsheetXmlStyles::getStylesXml();

        $xml .= " <Worksheet ss:Name=\"Estaciones de Trabajo\">\n";
        $xml .= "  <Table>\n";
        $xml .= "   <Column ss:Width=\"110\"/><Column ss:Width=\"70\"/><Column ss:Width=\"100\"/><Column ss:Width=\"110\"/><Column ss:Width=\"50\"/><Column ss:Width=\"60\"/><Column ss:Width=\"60\"/><Column ss:Width=\"90\"/><Column ss:Width=\"110\"/><Column ss:Width=\"130\"/><Column ss:Width=\"75\"/><Column ss:Width=\"85\"/><Column ss:Width=\"80\"/><Column ss:Width=\"110\"/><Column ss:Width=\"80\"/><Column ss:Width=\"90\"/>\n";
        $xml .= "   <Row ss:Height=\"22\"><Cell ss:StyleID=\"HeaderTitle\"><Data ss:Type=\"String\">Inventario de Equipos</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"18\"><Cell ss:StyleID=\"HeaderSubtitle\"><Data ss:Type=\"String\">Gerencia General de ATIT • Estaciones de Trabajo</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Gerencia:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">GERENCIA GENERAL DE ATIT</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Responsable Regional:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">SOPORTE TÉCNICO REGIONAL</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"16\"><Cell ss:StyleID=\"MetaLabel\"><Data ss:Type=\"String\">Estados asociados:</Data></Cell><Cell ss:StyleID=\"MetaValue\"><Data ss:Type=\"String\">CARABOBO / SEDE VALLE SECO</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"20\"><Cell ss:Index=\"10\" ss:MergeAcross=\"4\" ss:StyleID=\"ColHeaderGroup\"><Data ss:Type=\"String\">ASIGNADO</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"24\">\n";
        $wsHeaders = ['TIPO DE EQUIPO', 'MARCA', 'MODELO', 'SERIAL', 'AÑO', 'MEMORIA', 'DISCOS', 'SISTEMA OPERATIVO', 'APLICATIVOS INSTALADOS', 'NOMBRE Y APELLIDO', 'CÉDULA', 'TELÉFONO', 'ESTADO', 'UNIDAD', 'CONDICIONES', 'OBSERVACION'];
        foreach ($wsHeaders as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . "</Data></Cell>\n";
        }
        $xml .= "   </Row>\n";
        foreach ($rows as $r) {
            $xml .= "   <Row ss:Height=\"18\">\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['type']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['brand']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['model']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['serial']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['year']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['ram']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['disk']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['os']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['apps']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['custodian']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['id_number']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['phone']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['state']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['unit']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCellCenter"><Data ss:Type="String">' . $esc($r['status']) . "</Data></Cell>\n";
            $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc($r['observation']) . "</Data></Cell>\n";
            $xml .= "   </Row>\n";
        }
        $xml .= "  </Table>\n";
        $xml .= " </Worksheet>\n";
        $xml .= "</Workbook>\n";

        return $xml;
    }

    /**
     * Construye un libro Excel para reportes genéricos (IPAM, cumplimiento, etc.).
     */
    public function buildGenericReportSpreadsheetXml(array $data): string
    {
        $esc = fn(?string $val) => htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
        $xml .= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
        $xml .= " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
        $xml .= " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
        $xml .= " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";

        $xml .= SpreadsheetXmlStyles::getStylesXml();

        $sheetName = substr($data['title'], 0, 30);
        $xml .= ' <Worksheet ss:Name="' . $esc($sheetName) . "\">\n";
        $xml .= "  <Table>\n";
        $xml .= '   <Row ss:Height="22"><Cell ss:StyleID="HeaderTitle"><Data ss:Type="String">' . $esc($data['title']) . "</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"18\"><Cell ss:StyleID=\"HeaderSubtitle\"><Data ss:Type=\"String\">Centro de Monitoreo &amp; Reportes NOC + ITAM</Data></Cell></Row>\n";
        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">Fecha de Emisión:</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">' . now()->format('d/m/Y H:i:s') . "</Data></Cell></Row>\n";

        if (!empty($data['summary'])) {
            $xml .= "   <Row ss:Height=\"12\"></Row>\n";
            foreach ($data['summary'] as $label => $val) {
                $xml .= '   <Row ss:Height="16"><Cell ss:StyleID="MetaLabel"><Data ss:Type="String">' . $esc($label) . ':</Data></Cell><Cell ss:StyleID="MetaValue"><Data ss:Type="String">' . $esc((string)$val) . "</Data></Cell></Row>\n";
            }
        }

        $xml .= "   <Row ss:Height=\"12\"></Row>\n";
        $xml .= "   <Row ss:Height=\"24\">\n";
        foreach ($data['headers'] as $h) {
            $xml .= '    <Cell ss:StyleID="ColHeader"><Data ss:Type="String">' . $esc($h) . "</Data></Cell>\n";
        }
        $xml .= "   </Row>\n";

        foreach ($data['rows'] as $r) {
            $xml .= "   <Row ss:Height=\"18\">\n";
            foreach ($r as $val) {
                $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $esc((string)$val) . "</Data></Cell>\n";
            }
            $xml .= "   </Row>\n";
        }

        $xml .= "  </Table>\n";
        $xml .= " </Worksheet>\n";
        $xml .= "</Workbook>\n";

        return $xml;
    }
}
