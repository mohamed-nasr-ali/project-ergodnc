<?php

namespace App\Providers;

use App\Models\Image;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Model::unguard(); allow unguard to all models

        //https://laravel-news.com/enforcing-morph-maps-in-laravel
        Relation::enforceMorphMap([
                                      'office' => Office::class,
                                      'user' => User::class
                                  ]);
    }
}
