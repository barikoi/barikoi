<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
class UpdateSqliteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:sqlite';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Sqlite';

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
      $startTimer = microtime(true);
      $this->line("\nEmptying the database for you . . . ");
      DB::connection('sqlite')->table('places_3')->where('Address','LIKE','%%')->delete();

      $c1 = DB::table('places_3')->get();
      $this->line("\nInserting the database for you . . . ");
      $count = 0;
      foreach($c1 as $record){

        DB::connection('sqlite')->table('places_3')->insert(get_object_vars($record));
        $this->line("\n"+$count++);

      }
      $stopTimer = microtime(true);
      $this->line("\nDone!!");



    }
}
