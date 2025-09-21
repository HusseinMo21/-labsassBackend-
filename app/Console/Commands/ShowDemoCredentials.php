<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ShowDemoCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display demo login credentials for the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Demo Login Credentials');
        $this->line('');
        
        $this->info('👑 Admin Access:');
        $this->line('   Email: admin@dryasserlab.com');
        $this->line('   Password: DrYasserLab123456790@');
        $this->line('');
        
        $this->info('👥 Staff Access:');
        $this->line('   Email: zeinab@dryasserlab.com');
        $this->line('   Password: Zeinab12345678');
        $this->line('');
        $this->line('   Email: menna@dryasserlab.com');
        $this->line('   Password: Menna12345678');
        $this->line('');
        
        $this->info('👨‍⚕️ Doctor Access:');
        $this->line('   Email: doctor1@dryasserlab.com');
        $this->line('   Password: Doctor123456');
        $this->line('');
        $this->line('   Email: doctor2@dryasserlab.com');
        $this->line('   Password: Doctor123456');
        $this->line('');
        
        $this->info('🌐 Application URL: http://localhost:8000');
        $this->line('');
        $this->comment('Note: These credentials are for demo purposes only.');
        
        return 0;
    }
}