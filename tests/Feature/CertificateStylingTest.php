<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{CertificatePdf, Workflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CertificateStylingTest extends TestCase
{
    use RefreshDatabase;

    private function setupDesign(): array
    {
        Storage::fake('local');
        $admin = User::create(['name' => 'Sample Learner', 'email' => uniqid().'@example.test', 'password' => 'A-long-test-password', 'role' => 'admin', 'status' => 'active']);
        $program = Workflow::insert('programs', ['title' => 'Skills', 'slug' => uniqid(), 'category' => 'TVET', 'summary' => 'Text', 'body' => 'Text', 'status' => 'published']);
        $course = Workflow::insert('courses', ['title' => 'Sample course', 'program_id' => $program, 'summary' => 'Text', 'duration_hours' => 4, 'status' => 'published']);
        $pdf = app(CertificatePdf::class)->pdf(); $pdf->AddPage('L', 'A4');
        $file = UploadedFile::fake()->createWithContent('design.pdf', $pdf->Output('design.pdf', 'S'));
        $this->actingAs($admin)->put('/admin/certificates/'.$course.'/template', ['template' => $file, 'placements' => CertificatePdf::defaults()])->assertRedirect()->assertSessionHasNoErrors();
        return [$course, $admin];
    }

    public function test_text_styling_is_saved_and_applied_to_the_generated_pdf(): void
    {
        [$course] = $this->setupDesign();
        $placements = CertificatePdf::defaults();
        foreach ($placements as &$place) $place['enabled'] = false;
        unset($place);
        $placements['full_name'] = array_replace($placements['full_name'], ['enabled' => true, 'color' => '#cc3300', 'font_family' => 'dejavuserif', 'font_style' => 'BI', 'x' => 12, 'y' => 40, 'width' => 70]);
        $this->put('/admin/certificates/'.$course.'/template', ['placements' => $placements])->assertRedirect()->assertSessionHasNoErrors();
        $saved = json_decode(DB::table('certificate_templates')->value('placements'), true);
        $this->assertSame('#cc3300', $saved['full_name']['color']); $this->assertSame('dejavuserif', $saved['full_name']['font_family']); $this->assertSame('BI', $saved['full_name']['font_style']);
        $this->get('/admin/certificates/'.$course.'/template')->assertOk()->assertSee('Text colour')->assertSee('Font style')->assertSee('data-add-placement="full_name"', false)->assertSee('data-certificate-image', false);
        $this->app->instance(CertificatePdf::class, new class extends CertificatePdf {
            public function pdf(): \setasign\Fpdi\Tcpdf\Fpdi { $pdf = parent::pdf(); $pdf->setCompression(false); return $pdf; }
        });
        $bytes = $this->get('/admin/certificates/'.$course.'/preview.pdf')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('DejaVuSerif', $bytes);
        $this->assertStringContainsString('0.800000 0.200000 0.000000 rg', $bytes);
    }

    public function test_invalid_colours_fonts_and_styles_cannot_replace_the_saved_design(): void
    {
        [$course] = $this->setupDesign(); $original = DB::table('certificate_templates')->value('placements');
        foreach (['color' => 'red; background:url(https://example.test)', 'font_family' => '../../private', 'font_style' => 'javascript'] as $key => $value) {
            $placements = CertificatePdf::defaults(); $placements['full_name'][$key] = $value;
            $this->put('/admin/certificates/'.$course.'/template', ['placements' => $placements])->assertSessionHasErrors('placements.full_name.'.$key);
            $this->assertSame($original, DB::table('certificate_templates')->value('placements'));
        }
    }

    public function test_legacy_placements_get_default_styles_and_only_admin_can_save(): void
    {
        [$course, $admin] = $this->setupDesign(); $placements = CertificatePdf::defaults();
        foreach ($placements as &$place) unset($place['color'], $place['font_family'], $place['font_style']);
        unset($place);
        DB::table('certificate_templates')->update(['placements' => json_encode($placements)]);
        $this->get('/admin/certificates/'.$course.'/template')->assertOk()->assertSee('value="#14231e"', false);
        $this->put('/admin/certificates/'.$course.'/template', ['placements' => $placements])->assertSessionHasNoErrors();
        $saved = json_decode(DB::table('certificate_templates')->value('placements'), true);
        $this->assertSame('dejavusans', $saved['full_name']['font_family']);
        $admin->update(['role' => 'manager']);
        $this->put('/admin/certificates/'.$course.'/template', ['placements' => $placements])->assertForbidden();
    }
}
