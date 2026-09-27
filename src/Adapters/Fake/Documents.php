<?php
declare(strict_types=1);
namespace Ordely\Adapters\Fake;
use Ordely\Core\Data\Document;
final class Documents
{
    public static function pdf(): Document
    {
        $stream="BT /F1 18 Tf 35 100 Td (ORDELY TEST DOCUMENT - NOT VALID) Tj ET";
        $objects=[
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 500 160] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];
        $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach($objects as $index=>$object){$offsets[]=strlen($pdf);$pdf.=($index+1)." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";
        foreach(array_slice($offsets,1) as $offset){$pdf.=sprintf("%010d 00000 n \n",$offset);}
        $pdf.="trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        return new Document($pdf,'application/pdf');
    }
}
