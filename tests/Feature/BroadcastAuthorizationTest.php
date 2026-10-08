<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BroadcastAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create(['name'=>'Test '.$role,'email'=>uniqid().'@example.test',
            'password'=>'A-long-test-password','role'=>$role,'status'=>'active']);
    }

    public function test_unprivileged_tokens_cannot_broadcast(): void
    {
        Http::preventStrayRequests();
        $service=\Mockery::mock(NotificationService::class);
        $service->shouldNotReceive('sendToParticipants');
        $service->shouldNotReceive('sendSmsToParticipants');
        $this->app->instance(NotificationService::class,$service);
        foreach(['participant','instructor','mentor'] as $role){
            $token=$this->account($role)->createToken('test')->plainTextToken;
            $this->withToken($token)->postJson('/api/notifications/push',['title'=>'Title','body'=>'Body'])->assertForbidden();
            $this->withToken($token)->postJson('/api/sms',['body'=>'Body'])->assertForbidden();
        }
    }

    public function test_managers_can_broadcast_only_validated_payloads(): void
    {
        $service=\Mockery::mock(NotificationService::class);
        $service->shouldReceive('sendToParticipants')->twice()->with('Title','Body',['screen'=>'learn'])->andReturn(2);
        $service->shouldReceive('sendSmsToParticipants')->twice()->with('Notification','Body')->andReturn(2);
        $this->app->instance(NotificationService::class,$service);
        foreach(['admin','manager'] as $role){
            $token=$this->account($role)->createToken('test')->plainTextToken;
            $this->withToken($token)->postJson('/api/notifications/push',[
                'title'=>'Title','body'=>'Body','data'=>['screen'=>'learn'],'untrusted'=>'excluded',
            ])->assertOk()->assertJsonPath('sent',2);
            $this->withToken($token)->postJson('/api/sms',['body'=>'Body'])->assertOk()->assertJsonPath('sent',2);
            $this->withToken($token)->postJson('/api/sms',['body'=>str_repeat('x',1601)])->assertUnprocessable();
        }
    }

    public function test_guests_and_inactive_accounts_cannot_broadcast(): void
    {
        $this->postJson('/api/sms',['body'=>'Body'])->assertUnauthorized();
        $user=$this->account('admin');$user->update(['status'=>'inactive']);
        $this->withToken($user->createToken('test')->plainTextToken)
            ->postJson('/api/sms',['body'=>'Body'])->assertForbidden();
    }
}
