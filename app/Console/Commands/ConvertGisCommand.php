<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
class ConvertGisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gis:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert database to GIS database';

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
      DB::table('places_2')->truncate();
      $this->line("\nCopying the database for you . . . ");
      DB::select("INSERT INTO places_2 (
      id,
      Address,
      longitude,
      latitude,
      city,area,postCode,pType,subtype,uCode,created_at,route_description,location)
      SELECT id,Address,
              longitude,
              latitude,
              city,area,postCode,pType,subtype,uCode,created_at, route_description,GeomFromText(CONCAT('POINT(',longitude, ' ', latitude,')'))
       FROM places");
       $stopTimer = microtime(true);
       $totalTime = $stopTimer-$startTimer;
       $this->line("\nTime: "+$totalTime);
       $this->line("\nDone");



    }
}
