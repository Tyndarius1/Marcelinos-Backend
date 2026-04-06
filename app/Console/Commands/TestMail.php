<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-mail {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify mail configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'libamarkjefferson@gmail.com';

        $this->info('Sending test email to: ' . $email);

        try {
            Mail::raw('This is a test email from Marcelinos Backend.', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email');
            });

            $this->info('Test email sent successfully!');
            \Log::info('Test email sent to ' . $email);
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            \Log::error('Test email failed: ' . $e->getMessage());
        }
    }
}
