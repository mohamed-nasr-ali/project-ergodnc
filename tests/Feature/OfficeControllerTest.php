<?php

namespace Tests\Feature;

use App\Enums\OfficeApprovalStatus;
use App\Enums\ReservationStatus;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

    public function testItFilterByUserId(): void
    {
        Office::factory(3)->create();

        $host = User::factory()->create();

        $office = Office::factory()->for($host)->create();
        $response = $this->get(route('api.offices.index').'?user_id='.$host->id);

        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    public function testItFiltersByVisitorId(): void
    {
        Office::factory(3)->create();

        $user = User::factory()->create();

        $office = Office::factory()->create();
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(route('api.offices.index').'?visitor_id='.$user->id);

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

    /** @test */
    public function itCreatesAnOffice(): void
    {
        $admin = User::factory(['is_admin' => true])->create();

        $user = User::factory()->createQuietly();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $this->actingAs($user);

        Notification::fake();

        $data = [
            'title' => $title = $this->faker->company(),
            'description' => $this->faker->text(),
            'address_line1' => $this->faker->address(),
            'lat' => '39.74051727562952',
            'lng' => '-8.770375324893696',
            'price_per_day' => 10000,
            'monthly_discount' => 5,
            'tags' => [
                $tag1->id,
                $tag2->id
            ]
        ];

        $response = $this->postJson(route('api.offices.create'), $data);
        $response->assertCreated()
            ->assertJsonPath('data.title', $title)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.approval_status', OfficeApprovalStatus::APPROVAL_PENDING->value)
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas(Office::class, Arr::except($data, 'tags'));
        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /** @test */
    public function itDoesntAllowCreatingIfScopeIsNotProvided(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test', []);

        $response = $this->postJson(
            route('api.offices.create'),
            [],
            ['Authorization' => "Bearer $token->plainTextToken"]
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function itDoesntAllowUpdatingIfScopeIsNotProvided(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $token = $user->createToken('test', []);

        $response = $this->putJson(
            route('api.offices.update', $office),
            [],
            ['Authorization' => "Bearer $token->plainTextToken"]
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function itAllowUpdatingIfScopeIsProvided(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $token = $user->createToken('test', ['office.update']);

        $response = $this->putJson(
            route('api.offices.update', $office),
            [],
            ['Authorization' => "Bearer $token->plainTextToken"]
        );

        $response->assertStatus(Response::HTTP_OK);
    }

    /** @test */
    public function itUpdateAnOffice(): void
    {
        $user = User::factory()->createQuietly();
        $tags = Tag::factory(3)->create();
        $otherTag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();


        $office->tags()->attach($tags);

        $this->actingAs($user);

        $title = $this->faker->company();

        $response = $this->putJson(
            route('api.offices.update', $office),
            ['title' => $title, 'tags' => [$tags[0]->id, $otherTag->id]]
        );
        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $otherTag->id)
            ->assertJsonPath('data.title', $title);
    }

    /** @test */
    public function itDoesntUpdateOfficeThatDoesntBelongToUser(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser);

        $title = $this->faker->company();

        $response = $this->putJson(route('api.offices.update', $office), ['title' => $title]);
        $response->assertForbidden();
    }

    /** @test */
    public function itMarksOfficeToPendingWhenAttributesIsDirty(): void
    {
        $admin = User::factory(['is_admin' => true])->create();

        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);


        $response = $this->putJson(route('api.offices.update', $office), ['lat' => '40.54654654564']);

        $response->assertOk();
        $this->assertDatabaseHas(
            Office::class,
            ['id' => $office->id, 'approval_status' => OfficeApprovalStatus::APPROVAL_PENDING]
        );

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /** @test */
    public function itCanDeleteOffices(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $this->deleteJson(route('api.offices.destroy', $office))->assertOk();

        $this->assertSoftDeleted($office);
    }

    /** @test */
    public function itCantDeleteOfficeThatHasReservations(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $this->deleteJson(route('api.offices.destroy', $office))
            ->assertInvalid('office')
            ->assertUnprocessable();

        $this->assertNotSoftDeleted($office);
    }

    public function testItListOfficesIncludingHiddenAndApprovedIfFilteringForCurrentLoggedInUser(): void
    {
        $user = User::factory()->create();
        Office::factory(3)->for($user)->create();

        Office::factory()->for($user)->pending()->create();
        Office::factory()->for($user)->hidden()->create();

        $this->actingAs($user);

        $response = $this->get(route('api.offices.index')."?user_id=$user->id");

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    /** @test */
    public function itUpdateAnFeaturedImage(): void
    {
        $user = User::factory()->createQuietly();
        $office = Office::factory()->for($user)->create();


        $image = $office->images()->create(['path' => 'image.jpg']);

        $this->actingAs($user);

        $response = $this->putJson(
            route('api.offices.update', $office),
            ['featured_image_id' => $image->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.featured_image_id', $image->id);
    }

    /** @test */
    public function ifDeleteOfficeImagesWhenOfficeDeleted(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create(['path' => 'office_image.jpg']);
        $image = $office->images()->create(['path' => 'office_featured_image.jpg']);
        $this->actingAs($user);

        $this->deleteJson(route('api.offices.destroy', $office))
            ->assertOk();

        $this->assertSoftDeleted($office);
        $this->assertDeleted($image);

        Storage::assertMissing('office_featured_image.jpg');
    }

    /** @test */
    public function itFilterByTags(): void
    {
        $tags = Tag::factory(2)->create();

        $office = Office::factory()->hasAttached($tags)->create();
        Office::factory()->hasAttached($tags->first())->create();
        Office::factory()->create();

        $response = $this->getJson(
            route('api.offices.index')."?".http_build_query(['tags' => $tags->pluck('id')->toArray()])
        );
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id);
    }

    /** @test */
    public function itShowFullPathOfImage(): void
    {
        $office = Office::factory()->create();
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->getJson(route('api.offices.index'));
        $response->assertOk()
            ->assertJsonPath('data.0.images.0.path',storage_path('app/image.jpg'));
    }
}
