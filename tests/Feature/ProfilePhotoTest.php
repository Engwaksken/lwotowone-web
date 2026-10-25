<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_upload_view_and_remove_private_profile_photo(): void
    {
        Storage::fake('local');
        $learner = User::create([
            'name' => 'Learner',
            'email' => 'photo@example.test',
            'password' => 'A-long-test-password',
            'role' => 'participant',
            'status' => 'active',
        ]);
        $token = $learner->createToken('mobile')->plainTextToken;

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
        $this->withToken($token)->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->createWithContent('learner.png', $png),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ok', true);

        $path = $learner->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->withToken($token)->getJson('/api/snapshot')
            ->assertOk()->assertJsonPath('user.profile_photo_url', url('/api/profile/photo?version='.$learner->fresh()->updated_at->timestamp));
        $this->withToken($token)->get('/api/profile/photo')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->withToken($token)->deleteJson('/api/profile/photo')->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($learner->fresh()->profile_photo_path);
    }

    public function test_profile_photo_is_private_validated_and_participant_only(): void
    {
        Storage::fake('local');
        $learner = User::create([
            'name' => 'Learner', 'email' => 'photo2@example.test',
            'password' => 'A-long-test-password', 'role' => 'participant', 'status' => 'active',
        ]);
        $token = $learner->createToken('mobile')->plainTextToken;
        $this->withToken($token)->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->create('not-image.txt', 3, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->withToken($token)->getJson('/api/profile/photo')->assertNotFound();

        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager-photo@example.test',
            'password' => 'A-long-test-password', 'role' => 'manager', 'status' => 'active',
        ]);
        $this->actingAs($manager, 'sanctum')->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->create('manager.png', 3, 'image/png'),
        ], ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson('/api/profile/photo')->assertForbidden();
    }
}
