<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PigMarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_can_publish_listing_and_review_buyer_requests(): void
    {
        [$owner, $farm] = $this->farmWithOwner();

        $listingId = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/pig-listings", [
                'title' => 'Healthy weaners',
                'breed' => 'Large White',
                'age_weeks' => 10,
                'weight_kg' => 18.5,
                'quantity' => 6,
                'price_per_pig' => 18000,
                'currency' => 'KES',
                'description' => 'Vaccinated and ready for collection.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.farm_name', 'Marketplace farm')
            ->json('data.id');

        $this->postJson("/api/v1/marketplace/pigs/{$listingId}/inquiries", [
            'buyer_name' => 'Amina Buyer',
            'phone' => '+254700000005',
            'quantity' => 7,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $inquiryId = $this->postJson("/api/v1/marketplace/pigs/{$listingId}/inquiries", [
            'buyer_name' => 'Amina Buyer',
            'phone' => '+254700000005',
            'email' => 'amina@example.com',
            'quantity' => 2,
            'message' => 'Can I collect this weekend?',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->getJson('/api/v1/marketplace/pigs')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $listingId)
            ->assertJsonPath('data.0.inquiries', null);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/farms/{$farm->id}/pig-listings")
            ->assertOk()
            ->assertJsonPath('data.0.inquiries.0.buyer_name', 'Amina Buyer')
            ->assertJsonPath('data.0.inquiries.0.phone', '+254700000005');

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/farms/{$farm->id}/pig-listings/{$listingId}/inquiries/{$inquiryId}", [
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/farms/{$farm->id}/pig-listings/{$listingId}", [
                'status' => 'sold',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sold');

        $this->assertDatabaseHas('pig_inquiries', [
            'id' => $inquiryId,
            'pig_listing_id' => $listingId,
            'status' => 'accepted',
        ]);
    }

    public function test_buyers_cannot_request_unavailable_pigs_and_farms_are_isolated(): void
    {
        [$owner, $farm] = $this->farmWithOwner();
        $listing = $farm->pigListings()->create([
            'title' => 'Breeding gilt',
            'breed' => 'Landrace',
            'quantity' => 1,
            'price_per_pig' => 25000,
            'currency' => 'KES',
            'status' => 'unavailable',
            'created_by' => $owner->id,
        ]);

        $this->postJson("/api/v1/marketplace/pigs/{$listing->id}/inquiries", [
            'buyer_name' => 'Buyer',
            'phone' => '+254700000006',
            'quantity' => 1,
        ])->assertNotFound();

        $otherFarm = Farm::create(['name' => 'Other farm']);
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/farms/{$otherFarm->id}/pig-listings")
            ->assertForbidden();

        $this->postJson('/api/v1/marketplace/pigs', [
            'title' => 'Invalid public listing',
            'breed' => 'Mixed',
            'quantity' => 1,
            'price_per_pig' => 1,
            'currency' => 'KES',
        ])->assertMethodNotAllowed();
    }

    /** @return array{User, Farm} */
    private function farmWithOwner(): array
    {
        $owner = User::factory()->create(['role' => 'farmOwner']);
        $farm = Farm::create(['name' => 'Marketplace farm', 'location' => 'Nakuru']);
        $farm->users()->attach($owner->id);

        return [$owner, $farm];
    }
}
