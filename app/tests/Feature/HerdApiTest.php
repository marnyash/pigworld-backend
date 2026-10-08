<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HerdApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_owner_can_create_a_typed_animal_with_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'farmOwner']);
        $farm = Farm::create(['name' => 'Test farm']);
        $farm->users()->attach($user);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/farms/{$farm->id}/animals", [
                'tag' => 'PIG-BOAR-1',
                'type' => 'boar',
                'sex' => 'male',
                'weight_kg' => 45.5,
                'image' => UploadedFile::fake()->create('boar.jpg', 10, 'image/jpeg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.tag', 'PIG-BOAR-1')
            ->assertJsonPath('data.type', 'boar')
            ->assertJsonPath('data.sex', 'male')
            ->assertJsonPath('data.weight_kg', 45.5);

        $animal = $farm->animals()->where('tag', 'PIG-BOAR-1')->firstOrFail();
        $this->assertNotNull($animal->image_path);
        Storage::disk('public')->assertExists($animal->image_path);
        $this->assertStringContainsString(
            '/storage/animal-images/',
            $response->json('data.image_url'),
        );
    }

    public function test_animal_creation_rejects_unsupported_types_and_sexes(): void
    {
        $user = User::factory()->create(['role' => 'farmOwner']);
        $farm = Farm::create(['name' => 'Test farm']);
        $farm->users()->attach($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/animals", [
                'tag' => 'PIG-INVALID',
                'type' => 'unknown',
                'sex' => 'other',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'sex']);
    }

    public function test_farm_owner_can_replace_an_animal_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'farmOwner']);
        $farm = Farm::create(['name' => 'Test farm']);
        $farm->users()->attach($user);
        $oldImage = UploadedFile::fake()->create('old.jpg', 10, 'image/jpeg');
        $animal = $farm->animals()->create([
            'created_by' => $user->id,
            'tag' => 'PIG-1',
            'type' => 'sow',
            'sex' => 'female',
            'image_path' => $oldImage->store("animal-images/{$farm->id}", 'public'),
        ]);
        $oldImagePath = $animal->image_path;

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/farms/{$farm->id}/animals/{$animal->id}", [
                '_method' => 'PATCH',
                'image' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
            ])
            ->assertOk();

        $animal->refresh();
        $this->assertNotSame($oldImagePath, $animal->image_path);
        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($animal->image_path);
        $this->assertStringContainsString('/storage/animal-images/', $response->json('data.image_url'));
    }
}
