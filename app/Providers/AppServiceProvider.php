<?php
namespace App\Providers;

use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LendingPort;
use App\Application\Ports\UserAccounts;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\MostBorrowedReader;
use App\Application\Ports\BookCatalog;
use App\Adapters\Persistence\PostgresBookCatalog;
use App\Adapters\Persistence\PostgresMostBorrowedReader;
use App\Adapters\Persistence\PostgresUserAccounts;
use App\Adapters\Security\ArgonPasswordHasher;
use App\Adapters\Security\Hs256TokenIssuer;
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
        $this->app->bind(UserAccounts::class, PostgresUserAccounts::class);
        $this->app->bind(PasswordHasher::class, ArgonPasswordHasher::class);
        $this->app->bind(TokenIssuer::class, Hs256TokenIssuer::class);
        $this->app->bind(MostBorrowedReader::class, PostgresMostBorrowedReader::class);
        $this->app->bind(BookCatalog::class, PostgresBookCatalog::class);
    }

    public function boot(): void {}
}
