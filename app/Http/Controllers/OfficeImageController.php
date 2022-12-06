<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Office;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class OfficeImageController extends Controller
{
    /**
     * @param  Office  $office
     * @return JsonResource
     */
    public function store(Office $office):JsonResource
    {
        request()->validate(['image'=>['required','file','max:5000','mimes:jpg,png']]);

        $path = request()->file('image')->storePublicly('/',['disk'=>'public']);

        $image = $office->images()->create(['path'=>$path]);

        return  ImageResource::make($image);
    }

    /**
     * @throws Throwable
     * @param  Office  $office
     * @param Image $image
     */
    public function destroy(Office $office,Image $image): void
    {

        throw_if($office->images->doesntContain($image),
            ValidationException::withMessages(['image'=>'cannot delete un belonged image.'])
        );

        throw_if($office->images()->count() === 1,
            ValidationException::withMessages(['image'=>'cannot delete the only image.'])
        );

        throw_if($office->featured_image_id == $image->id,
                 ValidationException::withMessages(['image'=>'cannot delete the featured image.']));

        Storage::disk('public')->delete($image->path);

        $image->delete();
    }
}
