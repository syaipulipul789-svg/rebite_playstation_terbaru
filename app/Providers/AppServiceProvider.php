<?php

namespace App\Providers;

use App\Enums\PaymentMethod;
use App\Enums\ProductCategory;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Lazy loading dibiarkan aktif: beberapa helper display (audit logger,
        // rekap shift, transform API) memang memuat relasi secara lazily dan
        // aplikasi ini belum memakai N+1 yang mengganggu.
        Model::preventLazyLoading(false);
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        View::composer('*', function ($view) {
            $view->with([
                'appName' => config('app.name'),
                'unitStatusOptions' => UnitStatus::options(),
                'paymentMethodOptions' => PaymentMethod::options(),
                'productCategoryOptions' => ProductCategory::options(),
                'shiftStatusOptions' => ShiftStatus::options(),
            ]);
        });
    }
}
