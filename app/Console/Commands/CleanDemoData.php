<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDemoData extends Command
{
    protected $signature = 'demo:clean {--force : Remove sem confirmação}';
    protected $description = 'Remove somente os registros do lote de demonstração';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Remover apenas o lote '.DemoDataSeeder::BATCH.'?')) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            $batch = DemoDataSeeder::BATCH;
            $loanIds = DB::table('loans')->where('demo_batch', $batch)->pluck('id');
            $copyIds = DB::table('book_copies')->where('demo_batch', $batch)->pluck('id');
            $bookIds = DB::table('books')->where('demo_batch', $batch)->pluck('id');
            $workIds = DB::table('books')->whereIn('id', $bookIds)->whereNotNull('work_id')->pluck('work_id');

            DB::table('loan_renewals')->whereIn('loan_id', $loanIds)->delete();
            DB::table('copy_events')->whereIn('copy_id', $copyIds)->delete();
            DB::table('reservations')->where('demo_batch', $batch)->delete();
            DB::table('loans')->where('demo_batch', $batch)->delete();
            DB::table('copy_barcodes')->whereIn('copy_id', $copyIds)->delete();
            DB::table('book_copies')->where('demo_batch', $batch)->delete();
            DB::table('book_category')->whereIn('book_id', $bookIds)->delete();
            DB::table('book_sources')->whereIn('book_id', $bookIds)->delete();
            DB::table('books')->where('demo_batch', $batch)->delete();
            DB::table('book_works')->whereIn('id', $workIds)
                ->whereNotIn('id', DB::table('books')->whereNotNull('work_id')->select('work_id'))->delete();
            DB::table('borrowers')->where('demo_batch', $batch)->delete();
            DB::table('locations')->where('demo_batch', $batch)->delete();
        });

        $this->info('Dados de demonstração removidos. Registros anteriores foram preservados.');
        return self::SUCCESS;
    }
}
