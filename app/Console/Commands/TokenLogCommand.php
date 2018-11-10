<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
class TokenLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Log:token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log last months token counts and reset tokens';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

      DB::select("INSERT
        INTO token_log (user_id,reverse,auto,geo)
        SELECT user_id,reverse_geo_code_count,autocomplete_count,geo_code_count
        FROM tokens");
      DB::select("UPDATE tokens set reverse_geo_code_count = 0");
      DB::select("UPDATE tokens set geo_code_count = 0");
      DB::select("UPDATE tokens set autocomplete_count = 0");



    }
}
