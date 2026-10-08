<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnterpriseTest extends TestCase
{
    use RefreshDatabase;

    private function participant(): User
    {
        return User::create(['name'=>'Learner','email'=>uniqid().'@example.test',
            'password'=>'A-long-test-password','role'=>'participant','status'=>'active',
            'profile_complete'=>true,'learning_access_paid'=>true]);
    }

    private function enterprise(User $user, string $title='Farm'): int
    {
        return Workflow::run($user,'enterprise',[
            'title'=>$title,'sector'=>'Agriculture','idea'=>'Poultry','stage'=>'idea',
        ])['id'];
    }

    private function transaction(User $user, int $enterprise, string $type, float $amount, string $date, string $description='Sale'): int
    {
        return Workflow::insert('transactions',['user_id'=>$user->id,'enterprise_id'=>$enterprise,
            'type'=>$type,'amount'=>$amount,'occurred_on'=>$date,'description'=>$description]);
    }

    public function test_enterprise_page_renders_empty_and_populated_workflows_without_other_accounts(): void
    {
        $user=$this->participant();
        $this->actingAs($user)->get('/portal/enterprise')->assertOk()->assertSee('Create an enterprise');
        $enterprise=$this->enterprise($user,'Owned farm');
        $this->transaction($user,$enterprise,'income',2500,'2026-01-01','Owned sale');
        $other=$this->participant();$foreign=$this->enterprise($other,'Private enterprise');
        $this->transaction($other,$foreign,'income',9999,'2026-01-01','Private sale');
        $this->get('/portal/enterprise')->assertOk()->assertSee('Owned farm')->assertSee('Owned sale')
            ->assertSee('Save enterprise changes')->assertSee('Save transaction')
            ->assertDontSee('Private enterprise')->assertDontSee('Private sale');
    }

    public function test_enterprise_update_preserves_history_and_is_ownership_checked_and_replay_safe(): void
    {
        $user=$this->participant();$enterprise=$this->enterprise($user);
        $created=DB::table('enterprises')->find($enterprise)->created_at;
        $transaction=$this->transaction($user,$enterprise,'income',1000,'2026-01-01');
        $payload=['enterprise_id'=>$enterprise,'title'=>'Growing farm','sector'=>'Poultry',
            'idea'=>'New idea','business_plan'=>'New plan','stage'=>'growing',
            'client_id'=>'db27a5cb-cf94-4e34-978d-3bec2850e33a'];
        $this->actingAs($this->participant())->postJson('/api/actions/update-enterprise',$payload)->assertForbidden();
        $this->actingAs($user)->postJson('/api/actions/update-enterprise',$payload)->assertOk();
        $this->postJson('/api/actions/update-enterprise',$payload)->assertOk();
        $this->assertDatabaseHas('enterprises',['id'=>$enterprise,'title'=>'Growing farm','created_at'=>$created]);
        $this->assertDatabaseHas('transactions',['id'=>$transaction,'enterprise_id'=>$enterprise]);
        $this->assertDatabaseCount('sync_actions',1);
        $this->assertSame(1,DB::table('audit_logs')->where('action','update-enterprise')->count());
    }

    public function test_custom_dates_are_inclusive_and_description_search_does_not_change_totals(): void
    {
        $user=$this->participant();$enterprise=$this->enterprise($user);$second=$this->enterprise($user,'Shop');
        $first=$this->transaction($user,$enterprise,'income',1000,'2026-01-01','Egg sale');
        $this->transaction($user,$enterprise,'income',500,'2026-01-31','Bird sale');
        $this->transaction($user,$enterprise,'expense',250,'2026-01-15','Feed');
        $this->transaction($user,$enterprise,'income',9000,'2025-12-31','Earlier');
        $this->transaction($user,$enterprise,'income',8000,'2026-02-01','Later');
        $this->transaction($user,$second,'income',7000,'2026-01-01','Other enterprise');
        $this->actingAs($user)->get('/portal/enterprise?'.http_build_query([
            'period'=>'custom','start_date'=>'2026-01-01','end_date'=>'2026-01-31',
            'enterprise_id'=>$enterprise,'q'=>'Egg',
        ]))->assertOk()->assertViewHas('earnings',function($earnings)use($first){
            return (float)$earnings['income']===1500.0 && (float)$earnings['expenses']===250.0
                && (float)$earnings['net']===1250.0 && $earnings['transactions']->pluck('id')->all()===[$first];
        });
    }

    public function test_current_periods_apply_correct_boundaries(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-07 12:00:00'));
        $user=$this->participant();$enterprise=$this->enterprise($user);
        foreach(['2025-12-31'=>1,'2026-01-01'=>2,'2026-09-30'=>4,'2026-10-01'=>8,
            '2026-10-04'=>16,'2026-10-05'=>32,'2026-10-07'=>64] as $date=>$amount){
            $this->transaction($user,$enterprise,'income',$amount,$date);
        }
        $this->actingAs($user);
        foreach(['all'=>127,'year'=>126,'month'=>120,'week'=>96] as $period=>$expected){
            $this->get('/portal/enterprise?period='.$period)->assertOk()
                ->assertViewHas('earnings',fn($earnings)=>(float)$earnings['income']===(float)$expected);
        }
    }

    public function test_filters_reject_foreign_enterprises_and_invalid_date_ranges(): void
    {
        $user=$this->participant();$foreign=$this->enterprise($this->participant());
        $this->actingAs($user)->getJson('/portal/enterprise?enterprise_id='.$foreign)->assertForbidden();
        foreach([
            ['period'=>'invalid'],['period'=>'custom'],
            ['period'=>'custom','start_date'=>'2026-02-01','end_date'=>'2026-01-01'],
            ['period'=>'custom','start_date'=>'2026-02-30','end_date'=>'2026-03-01'],
        ] as $filters){
            $this->getJson('/portal/enterprise?'.http_build_query($filters))->assertUnprocessable();
        }
    }

    public function test_history_is_paginated_and_search_treats_wildcards_literally(): void
    {
        $user=$this->participant();$enterprise=$this->enterprise($user);
        for($i=0;$i<21;$i++)$this->transaction($user,$enterprise,'income',100,'2026-01-01','Regular sale');
        $this->transaction($user,$enterprise,'income',50,'2026-01-01','100%_! sale');
        $this->actingAs($user)->get('/portal/enterprise')->assertOk()
            ->assertViewHas('earnings',fn($e)=>$e['transactions']->count()===20 && $e['transactions']->total()===22);
        $this->get('/portal/enterprise?q='.urlencode('%_!'))->assertOk()
            ->assertViewHas('earnings',fn($e)=>$e['transactions']->total()===1 && (float)$e['income']===2150.0);
    }
}
