<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @test
     * @return void
     */
    public function itUploadsAndImageAndStoreItUnderTheOffice(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post(route('api.offices.images.store', $office->id), [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::disk('public')->assertExists($response->json('data.path'));
    }

    /** @test */
    public function itDeleteAndImage(): void
    {
        Storage::fake('public')->put('/office_image.jpg', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image1 = $office->images()->create(['path' => 'office_image.jpg']);
        $image2 = $office->images()->create(['path' => 'image.jpg']);

        $this->actingAs($user);

        $response = $this->deleteJson(
            route('api.offices.images.destroy', ['office' => $office->id, 'image' => $image1->id])
        );

        $response->assertOk();
        $this->assertDeleted(Image::class, ['id' => $image1->id]);
        $this->assertModelMissing($image1);
        $this->assertModelExists($image2);

        Storage::disk('public')->assertMissing('office_image.jpg');
    }

    /** @test */
    public function itDoesntDeleteTheOnlyImage(): void
    {
        Storage::fake('public')->put('/office_image.jpg', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create(['path' => 'office_image.jpg']);

        $this->actingAs($user);

        $response = $this->deleteJson(
            route('api.offices.images.destroy', ['office' => $office->id, 'image' => $image->id])
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('image');

        Storage::disk('public')->assertExists('office_image.jpg');
    }

    /** @test */
    public function itDoesntDeleteTheFeaturedImage(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create(['path' => 'office_image.jpg']);
        $image = $office->images()->create(['path' => 'office_featured_image.jpg']);

        $office->update(['featured_image_id' => $image->id]);


        $this->actingAs($user);

        $response = $this->deleteJson(
            route('api.offices.images.destroy', ['office' => $office->id, 'image' => $image->id])
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('image');
    }

    /** @test */
    public function itDoesntDeleteTheImageBelongsToOtherResource(): void
    {
        $user = User::factory()->create();
        $office1 = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $office1->images()->create(['path' => 'office_image.jpg']);
        $image = $office2->images()->create(['path' => 'office_featured_image.jpg']);

        $this->actingAs($user);

        $response = $this->deleteJson(
            route('api.offices.images.destroy', ['office' => $office1->id, 'image' => $image->id])
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image'=>'cannot delete un belonged image.']);
    }
}
