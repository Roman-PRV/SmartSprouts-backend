<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API contract: flat shape (no "data" envelope) for single + non-paginated
        // collection endpoints. Pagination, when needed, should opt into envelope
        // via dedicated PaginatedResourceCollection subclasses that explicitly
        // return {data, meta} from toArray().
        JsonResource::withoutWrapping();
    }
}
