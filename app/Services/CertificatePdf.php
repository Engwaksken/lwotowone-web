<?php
namespace App\Services;

use Illuminate\Support\Facades\{DB,Storage};
use Carbon\Carbon;
use setasign\Fpdi\Tcpdf\Fpdi;

class CertificatePdf
{
    public const FIELDS=['full_name'=>'Full name','course'=>'Course title','period'=>'Learning period','duration'=>'Course duration','issue_date'=>'Issue date','reference'=>'Certificate reference','learner_no'=>'Learner number','instructor'=>'Instructor'];
    public const FONTS=['dejavusans'=>'Sans serif','dejavuserif'=>'Serif','dejavusansmono'=>'Monospace'];
    public const STYLES=[''=>'Regular','B'=>'Bold','I'=>'Italic','BI'=>'Bold italic'];
    public static function defaults(): array {
        $out=[];foreach(array_keys(self::FIELDS) as $index=>$field)$out[$field]=['enabled'=>in_array($field,['full_name','course','period','issue_date','reference']),'x'=>10,'y'=>30+$index*7,'width'=>80,'font_size'=>$field==='full_name'?24:14,'align'=>'C','color'=>'#14231e','font_family'=>'dejavusans','font_style'=>''];return $out;
    }
    public function pdf(): Fpdi {if(!defined('K_TCPDF_EXTERNAL_CONFIG'))define('K_TCPDF_EXTERNAL_CONFIG',true);if(!defined('K_TCPDF_THROW_EXCEPTION_ERROR'))define('K_TCPDF_THROW_EXCEPTION_ERROR',true);if(!defined('K_PATH_CACHE'))define('K_PATH_CACHE',storage_path('framework/cache/'));$pdf=new CertificateDocument();$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);$pdf->SetMargins(0,0,0);$pdf->SetAutoPageBreak(false,0);return $pdf;}
    public function size(string $path,string $format): array {
        if($format==='pdf'){$pdf=$this->pdf();$pages=$pdf->setSourceFile($path);if($pages!==1)throw new \RuntimeException('Upload a single-page certificate PDF.');$size=$pdf->getTemplateSize($pdf->importPage(1));return [$size['width'],$size['height']];}
        $image=getimagesize($path);if(!$image||$image[0]>10000||$image[1]>10000||($format==='png'&&$image['mime']!=='image/png')||($format==='jpg'&&$image['mime']!=='image/jpeg'))throw new \RuntimeException('Use a valid image no larger than 10,000 pixels per side.');
        $width=297;$height=297*$image[1]/$image[0];$pdf=$this->pdf();$pdf->AddPage($width>$height?'L':'P',[$width,$height]);$pdf->Image($path,0,0,$width,$height,strtoupper($format));return [$width,$height];
    }
    public function render(object $certificate,?object $template=null): string {
        $user=DB::table('users')->find($certificate->user_id);$course=DB::table('courses')->find($certificate->course_id);$enrollment=DB::table('enrolments')->where('user_id',$user->id)->where('course_id',$course->id)->first();
        $data=['full_name'=>$user->name,'course'=>$course->title,'period'=>Carbon::parse($enrollment?->created_at??now())->format('d M Y').' – '.Carbon::parse($certificate->recommended_at)->format('d M Y'),'duration'=>$course->duration_hours.' hours','issue_date'=>Carbon::parse($certificate->recommended_at)->format('d F Y'),'reference'=>$certificate->reference,'learner_no'=>$user->learner_no??'','instructor'=>DB::table('users')->where('id',$certificate->recommended_by)->value('name')];
        $pdf=$this->pdf();$width=$template?(float)$template->width_mm:297;$height=$template?(float)$template->height_mm:210;
        $pdf->AddPage($width>$height?'L':'P',[$width,$height]);
        if($template){$path=Storage::disk('local')->path($template->file_path);if($template->format==='pdf'){$pdf->setSourceFile($path);$pdf->useTemplate($pdf->importPage(1),0,0,$width,$height);}else $pdf->Image($path,0,0,$width,$height,strtoupper($template->format));$placements=json_decode($template->placements,true);}
        else {$placements=self::defaults();$pdf->SetFont('dejavusans','B',28);$pdf->SetXY(10,20);$pdf->Cell(277,15,'Certificate of Completion',0,0,'C');}
        foreach($placements as $field=>$place){if(empty($place['enabled'])||!isset($data[$field]))continue;$font=$place['font_family']??'dejavusans';$style=$place['font_style']??'';$pdf->SetFont(array_key_exists($font,self::FONTS)?$font:'dejavusans',array_key_exists($style,self::STYLES)?$style:'',(float)$place['font_size']);$color=$place['color']??'#14231e';if(!preg_match('/^#[0-9a-f]{6}$/i',$color))$color='#14231e';$pdf->SetTextColor(hexdec(substr($color,1,2)),hexdec(substr($color,3,2)),hexdec(substr($color,5,2)));$pdf->SetXY($width*$place['x']/100,$height*$place['y']/100);$pdf->MultiCell($width*$place['width']/100,0,(string)$data[$field],0,$place['align'],false,1,'','',true,0,false,true,0,'T',true);}
        return $pdf->Output('certificate.pdf','S');
    }
}
