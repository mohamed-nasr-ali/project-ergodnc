<?php

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::all()
        );
    }
}
