<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    /** @test */
    public function itListReservationsThatBelongsToTheUser(): void
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create();

        $image = $reservation->office->images()->create(['path' => 'office_image.jpg']);

        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory()->for($user)->count(2)->create();
        Reservation::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->getJson(route('api.reservations.show'));
        $response->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

}
