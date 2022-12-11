<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    /** @test */
    public function itListReservationsThatBelongsToTheUser(): void
    {
        $user = User::factory()->create();

        [$reservation] = Reservation::factory()->count(2)->for($user)->create();

        $image = $reservation->office->images()->create(['path' => 'office_image.jpg']);

        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->getJson(route('api.user.reservations.show'));
        $response->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

    /** @test */
    public function itListReservationsFilteredByDateRange(): void
    {
        $user = User::factory()->create();

        $fromDate = '2021-03-03';
        $toDate = '2021-04-04';
        [$reservation1,$reservation2,$reservation3]=Reservation::factory()->sequence(
            //in the date range
            ['start_date' => '2021-03-01', 'end_date' => '2021-04-01'],
            ['start_date' => '2021-03-25', 'end_date' => '2021-04-15'],
            ['start_date' => '2021-03-25', 'end_date' => '2021-03-29'],
            //out the date range
            ['start_date' => '2021-02-25', 'end_date' => '2021-03-01'],
            ['start_date' => '2021-05-25', 'end_date' => '2021-06-01'],
            //in the range belongs to a different user
            ['start_date' => '2021-03-05', 'end_date' => '2021-03-21','user_id'=>User::factory()->create()->id]

        )->count(6)
        ->for($user)
        ->create();

        $this->actingAs($user);

        $response = $this->getJson(route('api.user.reservations.show')."?".http_build_query([
            'from_date'=>$fromDate,
            'to_date'=>$toDate
       ]));
        $response->assertJsonCount(3, 'data');

        $this->assertEqualsCanonicalizing([$reservation1->id,$reservation2->id,$reservation3->id], $response->json('data.*.id'));
    }

    /** @test */
    public function itListedActiveReservationsOnly(): void
    {
        $user = User::factory()->create();

        $reservation1=Reservation::factory()
            ->for($user)
            ->active()
            ->create();
        $reservation2=Reservation::factory()
            ->for($user)
            ->cancelled()
            ->create();

        $this->actingAs($user);

        $response = $this->getJson(route('api.user.reservations.show')."?".http_build_query(['status'=>ReservationStatus::STATUS_ACTIVE->value]));

        $response->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reservation1->id)
        ->assertJsonMissing(['id'=>$reservation2->id]);
    }
    /** @test */
    public function itListedReservationsBelongsToOffice(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $reservation1=Reservation::factory()
            ->for($user)
            ->for($office)
            ->create();
        $reservation2=Reservation::factory()
            ->for($user)
            ->create();

        $this->actingAs($user);

        $response = $this->getJson(route('api.user.reservations.show')."?".http_build_query(['office_id'=>$office->id]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id)
            ->assertJsonPath('data.0.office.id', $office->id)
            ->assertJsonMissing(['id'=>$reservation2->id]);
    }


}
