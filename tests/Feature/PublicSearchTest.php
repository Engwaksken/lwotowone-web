<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_search_returns_matching_public_content_and_excludes_drafts(): void
    {
        $now=now();
        DB::table('programs')->insert([
            ['title'=>'Climate 100%_! training','slug'=>'climate-training','category'=>'TVET','summary'=>'Practical green skills','body'=>'Learn and practise','status'=>'published','created_at'=>$now,'updated_at'=>$now],
            ['title'=>'Climate private draft','slug'=>'climate-private','category'=>'TVET','summary'=>'Hidden programme','body'=>'Draft content','status'=>'draft','created_at'=>$now,'updated_at'=>$now],
        ]);
        DB::table('pages')->insert([
            'title'=>'Climate learning guide','slug'=>'climate-guide','body'=>'Public learning resources','status'=>'published','meta_description'=>null,'created_at'=>$now,'updated_at'=>$now,
        ]);

        $this->get('/search?q='.urlencode('%_!'))->assertOk()->assertSee('Climate 100%_! training')
            ->assertDontSee('Climate private draft')->assertSee('/explore/programs/1');
        $this->get('/search?q=learning')->assertOk()->assertSee('Climate learning guide')->assertSee('/pages/climate-guide');
    }

    public function test_public_listing_has_no_page_level_search_form(): void
    {
        $this->get('/explore/programs')->assertOk()->assertSee('Search the site')->assertDontSee('No published items match your search.');
    }
}
