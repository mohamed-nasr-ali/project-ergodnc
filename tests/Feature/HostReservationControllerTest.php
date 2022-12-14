<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class HostReservationControllerTest extends TestCase
{
    /** @test */
    public function itListReservationsThatBelongsToTheUser(): void
    {
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();
        $user = User::factory()->create();

        [$reservation] = Reservation::factory()->count(2)->for($user)->for($office)->create();

        $image = $reservation->office->images()->create(['path' => 'office_image.jpg']);

        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory()->count(3)->create();

        $this->actingAs($host);

        $response = $this->getJson(route('api.host.reservations.show'));
        $response->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

    /** @test */
    public function itListReservationsFilteredByDateRange(): void
    {
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();
        $user = User::factory()->create();


        $fromDate = '2021-03-03';
        $toDate = '2021-04-04';
        $reservations=Reservation::factory()
            ->for($user)
            ->for($office)
            ->createMany([
                //in the date range
                ['start_date' => '2021-03-01', 'end_date' => '2021-04-01'],
                ['start_date' => '2021-03-25', 'end_date' => '2021-04-15'],
                ['start_date' => '2021-03-25', 'end_date' => '2021-03-29'],
                //start before range and ended after range
                ['start_date' => '2021-02-25', 'end_date' => '2021-05-01'],
                //out the date range
                ['start_date' => '2021-02-25', 'end_date' => '2021-03-01'],
                ['start_date' => '2021-05-25', 'end_date' => '2021-06-01'],
            ]);

        $this->actingAs($host);

        $response = $this->getJson(route('api.host.reservations.show')."?".http_build_query(['from_date'=>$fromDate, 'to_date'=>$toDate]));
        $response->assertJsonCount(4, 'data');

        $this->assertEqualsCanonicalizing($reservations->take(4)->pluck('id')->toArray(), $response->json('data.*.id'));
    }

    /** @test */
    public function itListedActiveReservationsOnly(): void
    {
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();
        $user = User::factory()->create();

        $reservation1=Reservation::factory()
            ->for($user)
            ->for($office)
            ->active()
            ->create();
        $reservation2=Reservation::factory()
            ->for($user)
            ->for($office)
            ->cancelled()
            ->create();

        $this->actingAs($host);

        $response = $this->getJson(route('api.host.reservations.show')."?".http_build_query(['status'=>ReservationStatus::STATUS_ACTIVE->value]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id)
            ->assertJsonMissing(['id'=>$reservation2->id]);
    }
    /** @test */
    public function itListedReservationsBelongsToOffice(): void
    {
        $user = User::factory()->create();
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $reservation1=Reservation::factory()
            ->for($user)
            ->for($office)
            ->create();
        $reservation2=Reservation::factory()
            ->for($user)
            ->create();

        $this->actingAs($host);

        $response = $this->getJson(route('api.host.reservations.show')."?".http_build_query(['office_id'=>$office->id]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id)
            ->assertJsonPath('data.0.office.id', $office->id)
            ->assertJsonMissing(['id'=>$reservation2->id]);
    }
    /** @test */
    public function itListedReservationsForHostUserByReservationUserId(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $reservation1=Reservation::factory()
            ->for($user)
            ->for($office)
            ->create();
        $reservation2=Reservation::factory()
            ->for($otherUser)
            ->for($office)
            ->create();

        $this->actingAs($host);

        $response = $this->getJson(route('api.host.reservations.show')."?".http_build_query(['user_id'=>$user->id]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id)
            ->assertJsonPath('data.0.office.id', $office->id)
            ->assertJsonMissing(['id'=>$reservation2->id]);
    }


}
