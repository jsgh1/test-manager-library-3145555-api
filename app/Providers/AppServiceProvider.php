<?php
namespace App\Providers;

use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LendingPort;
use App\Adapters\Time\SystemClock;
use App\Adapters\Identity\LaravelIdGenerator;
use App\Adapters\Persistence\PostgresLendingAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only the composition root knows the port implementations.
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, LaravelIdGenerator::class);
        $this->app->bind(LendingPort::class, PostgresLendingAdapter::class);
    }

    public function boot(): void {}
}
