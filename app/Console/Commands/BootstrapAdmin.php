<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class BootstrapAdmin extends Command {
    protected $signature = 'library:bootstrap-admin';
    protected $description = 'Provision the initial admin using environment credentials';
    public function handle(): int {
        $name = strtolower(trim((string)env('ADMIN_USERNAME','')));
        $password = (string) env('ADMIN_PASSWORD','');
        if ($name === '' || strlen($password) < 8) {
            $this->error('ADMIN_USERNAME and a strong ADMIN_PASSWORD are required.');
            return self::FAILURE;
        }
        $existing = DB::table('library.user')->where('username',$name)->first();
        if ($existing) {
            if ($existing->role !== 'admin') {
                $this->error('Bootstrap username belongs to a non-admin.');
                return self::FAILURE;
            }
            $this->info('Admin already exists; no credentials changed.');
            return self::SUCCESS;
        }
        DB::table('library.user')->insert([
            'id'=>(string)Str::uuid(),'username'=>$name,
            'password_hash'=>password_hash($password,PASSWORD_ARGON2ID),'role'=>'admin'
        ]);
        $this->info('Initial administrator provisioned.');
        return self::SUCCESS;
    }
}
