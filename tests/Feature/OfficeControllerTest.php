<?php

namespace Tests\Feature;

use App\Enums\OfficeApprovalStatus;
use App\Enums\ReservationStatus;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     * @test
     */
    public function itCanListOffices(): void
    {
        Office::factory(3)->create();

        $response = $this->get(route('api.offices.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'links', 'meta'], $response->json());
        $this->assertNotNull($response->json('data')[0]['id']);
    }

    public function testItOnlyListOfficesWhereApprovedAndNotHidden(): void
    {
        Office::factory(3)->create();

        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => OfficeApprovalStatus::APPROVAL_PENDING->value]);

        $response = $this->get(route('api.offices.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function testItFilterByHostId(): void
    {
        Office::factory(3)->create();

        $host = User::factory()->create();

        $office = Office::factory()->for($host)->create();
        $response = $this->get(route('api.offices.index').'?host_id='.$host->id);

        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function testItFiltersByUserId(): void
    {
        Office::factory(3)->create();

        $user = User::factory()->create();

        $office = Office::factory()->create();
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(route('api.offices.index').'?user_id='.$user->id);

        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function testItIncludesImagesTagsAndUser(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get(route('api.offices.index'));

        $response->assertOk();
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['images']);
    }

    public function testItReturnsTheNumberOfActiveReservations(): void
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status' => ReservationStatus::STATUS_ACTIVE->value]);
        Reservation::factory()->for($office)->create(['status' => ReservationStatus::STATUS_CANCELLED->value]);

        $response = $this->get(route('api.offices.index'));
        $response->assertOk();

        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    public function testItOrdersByDistanceWhenCoordinatesAreProvides(): void
    {
        Office::factory()->create([
                                      'lat' => '39.74051727562952',
                                      'lng' => '-8.770375324893696',
                                      'title' => 'Leiria'
                                  ]);

        Office::factory()->create([
                                      'lat' => '39.07753883078113',
                                      'lng' => '-9.281266331143293',
                                      'title' => 'Torres Vedras'
                                  ]);

        $response = $this->get('/api/offices?lat=38.720661384644046&lng=-9.16044783453807');

        $response->assertOk();
        $this->assertEquals('Torres Vedras', $response->json('data')[0]['title']);
        $this->assertEquals('Leiria', $response->json('data')[1]['title']);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals('Leiria', $response->json('data')[0]['title']);
        $this->assertEquals('Torres Vedras', $response->json('data')[1]['title']);
    }

    /** @test */
    public function itShowsTheOffice(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get(route('api.offices.show', $office->id));

        $response->assertOk();
        $this->assertEquals($office->id, $response->json('data')['id']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertNotEmpty($response->json('data')['tags']);
        $this->assertNotEmpty($response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }


}
