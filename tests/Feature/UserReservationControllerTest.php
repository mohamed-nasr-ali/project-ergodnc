<?php

namespace Tests\Feature;

use App\Enums\OfficeApprovalStatus;
use App\Enums\ReservationStatus;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NewHostReservation;
use App\Notifications\NewUserReservation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
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
        [$reservation1, $reservation2, $reservation3, $reservation4] = Reservation::factory()->sequence(
        //in the date range
            ['start_date' => '2021-03-01', 'end_date' => '2021-04-01'],
            ['start_date' => '2021-03-25', 'end_date' => '2021-04-15'],
            ['start_date' => '2021-03-25', 'end_date' => '2021-03-29'],
            //start before range and ended after range
            ['start_date' => '2021-02-25', 'end_date' => '2021-05-01'],
            //out the date range
            ['start_date' => '2021-02-25', 'end_date' => '2021-03-01'],
            ['start_date' => '2021-05-25', 'end_date' => '2021-06-01'],
            //in the range belongs to a different user
            ['start_date' => '2021-03-05', 'end_date' => '2021-03-21', 'user_id' => User::factory()->create()->id],
        )->count(7)
            ->for($user)
            ->create();

        $this->actingAs($user);

        $response = $this->getJson(
            route('api.user.reservations.show')."?".http_build_query([
                                                                         'from_date' => $fromDate,
                                                                         'to_date' => $toDate
                                                                     ])
        );
        $response->assertJsonCount(4, 'data');

        $this->assertEqualsCanonicalizing([$reservation1->id, $reservation2->id, $reservation3->id, $reservation4->id],
                                          $response->json('data.*.id'));
    }

    /** @test */
    public function itListedActiveReservationsOnly(): void
    {
        $user = User::factory()->create();

        $reservation1 = Reservation::factory()
            ->for($user)
            ->active()
            ->create();
        $reservation2 = Reservation::factory()
            ->for($user)
            ->cancelled()
            ->create();

        $this->actingAs($user);

        $response = $this->getJson(
            route('api.user.reservations.show')."?".http_build_query(
                ['status' => ReservationStatus::STATUS_ACTIVE->value]
            )
        );

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id)
            ->assertJsonMissing(['id' => $reservation2->id]);
    }

    /** @test */
    public function itListedReservationsBelongsToOffice(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $reservation1 = Reservation::factory()
            ->for($user)
            ->for($office)
            ->create();
        $reservation2 = Reservation::factory()
            ->for($user)
            ->create();

        $this->actingAs($user);

        $response = $this->getJson(
            route('api.user.reservations.show')."?".http_build_query(['office_id' => $office->id])
        );

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation1->id)
            ->assertJsonPath('data.0.office.id', $office->id)
            ->assertJsonMissing(['id' => $reservation2->id]);
    }

    /** @test */
    public function itMakesReservations(): void
    {
        $user = User::factory()->create();
        $office = Office::factory([
                                      'price_per_day' => 1_000,
                                      'monthly_discount' => 10
                                  ])->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(40),
        ]);

        $response->assertJsonPath('data.price', 36000)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.status', ReservationStatus::STATUS_ACTIVE->value);
    }

    /** @test */
    public function itCannotMakeReservationOnNonExistingOffice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => 1000,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(41),
        ]);

        $response->assertUnprocessable();
    }

    /** @test */
    public function itCannotMakeAReservationThatBelongsToTheUser(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $this->actingAs($user);
        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(41),
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['office_id' => 'invalid office!']);
    }

    /** @test */
    public function itCannotMakeAReservationOnTheSameDay(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addHours(25),
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_date' => 'You Cannot Make a Reservation For Only one day']);
    }

    /** @test */
    public function itCanMakeAReservationFor2Days(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(2),
        ]);
        $response->assertCreated();
    }

    /** @test */
    public function itCannotMakeReservationThatConflicting(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $from = now()->addDays(1)->toDateString();
        $to = now()->addDays(15)->toDateString();

        Reservation::factory([
                                 'start_date' => now()->addDays(2),
                                 'end_date' => $to
                             ])->for($office)->create();
        $this->actingAs($user);
        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => $from,
            'end_date' => $to
        ]);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => 'You Cannot Make a Reservation During This Time']);
    }

    /** @test */
    public function itCannotMakeReservationOnOfficeThatIsPending(): void
    {
        $user = User::factory()->create();
        $office = Office::factory(['approval_status' => OfficeApprovalStatus::APPROVAL_PENDING])->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(40)
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrorFor('office_id');
    }

    /** @test */
    public function itCannotMakeReservationOnOfficeThatHidden(): void
    {
        $user = User::factory()->create();
        $office = Office::factory(['hidden' => true])->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(40)
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrorFor('office_id');
    }

    /** @test */
    public function itCanMakeAReservationForOnTHeSameDay(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrorFor('start_date');
    }

    /** @test */
    public function itSendsNotificationToUserWhenReservationCreated(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);
        Notification::assertSentTo($user, NewUserReservation::class);
        $response->assertCreated();
    }

    /** @test */
    public function itSendsNotificationToOfficeHostWhenReservationCreated(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.user.reservations.create'), [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        Notification::assertSentTo($office->user, NewHostReservation ::class);

        $response->assertCreated();
    }

    /** @test */
    public function itCannotCancelReservationIfItNotBelongToAuthUser(): void
    {
        $user = User::factory()->create();
        $otherUser= User::factory()->create();
        $reservation= Reservation::factory()->for($user)->create();
        $this->actingAs($otherUser);
        $response=$this->deleteJson(route('api.user.reservations.cancel',$reservation->id));
        $response->assertStatus(ResponseAlias::HTTP_FORBIDDEN);
    }

    /** @test */
    public function itCannotCancelReservationThatAlreadyCanceled(): void
    {
        $user = User::factory()->create();
        $reservation= Reservation::factory(['status'=>ReservationStatus::STATUS_CANCELLED])->for($user)->create();
        $this->actingAs($user);
        $response=$this->deleteJson(route('api.user.reservations.cancel',$reservation->id));
        $response->assertForbidden();
    }

    /** @test */
    public function itCannotCancelReservationThatStartDateInThePast(): void
    {
        $user = User::factory()->create();
        $reservation= Reservation::factory(['start_date'=>now()->subDays(5)])->for($user)->create();
        $this->actingAs($user);
        $response=$this->deleteJson(route('api.user.reservations.cancel',$reservation->id));
        $response->assertForbidden();
    }

    /** @test */
    public function itCanCancelReservationThatActive(): void
    {
        $user = User::factory()->create();
        $reservation= Reservation::factory(['start_date'=>now()->addDays(5),'end_date'=>now()->addDays(20)])->for($user)->create();
        $this->actingAs($user);
        $response=$this->deleteJson(route('api.user.reservations.cancel',$reservation->id));
        $response->assertSuccessful()->assertJsonPath('data.status',ReservationStatus::STATUS_CANCELLED->value);
    }

}
