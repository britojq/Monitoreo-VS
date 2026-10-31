<?php

declare(strict_types=1);

namespace App\Services\Reports\Spreadsheet;

/**
 * Proveedor de estilos corporativos XML para documentos SpreadsheetML (Excel 2003+).
 */
class SpreadsheetXmlStyles
{
    /**
     * Retorna el bloque XML con los estilos compartidos para SpreadsheetML.
     */
    public static function getStylesXml(): string
    {
        $xml = " <Styles>\n";
        $xml .= "  <Style ss:ID=\"Default\" ss:Name=\"Normal\"><Alignment ss:Vertical=\"Center\"/><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#000000\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"HeaderTitle\"><Font ss:FontName=\"Calibri\" ss:Size=\"14\" ss:Bold=\"1\" ss:Color=\"#0F172A\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"HeaderSubtitle\"><Font ss:FontName=\"Calibri\" ss:Size=\"11\" ss:Bold=\"1\" ss:Color=\"#0284C7\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"MetaLabel\"><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#475569\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"MetaValue\"><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Color=\"#0F172A\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"ColHeader\"><Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" ss:WrapText=\"1\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/></Borders><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/><Interior ss:Color=\"#0F172A\" ss:Pattern=\"Solid\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"ColHeaderGroup\"><Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#000000\"/></Borders><Font ss:FontName=\"Calibri\" ss:Size=\"10\" ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/><Interior ss:Color=\"#0369A1\" ss:Pattern=\"Solid\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"DataCell\"><Alignment ss:Vertical=\"Center\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/></Borders><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Color=\"#0F172A\"/></Style>\n";
        $xml .= "  <Style ss:ID=\"DataCellCenter\"><Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\"/><Borders><Border ss:Position=\"Bottom\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/><Border ss:Position=\"Top\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/><Border ss:Position=\"Left\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/><Border ss:Position=\"Right\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" ss:Color=\"#CBD5E1\"/></Borders><Font ss:FontName=\"Calibri\" ss:Size=\"9\" ss:Color=\"#0F172A\"/></Style>\n";
        $xml .= " </Styles>\n";

        return $xml;
    }
}
