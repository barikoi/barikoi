<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
class FixCopyDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix the database with replace function';

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
      DB::table('places_3')->truncate();
      $this->line("\nCopying the database for you . . . ");
      DB::select("INSERT INTO places_3 (
      id,
      Address,
      new_address,
      alternate_address,
      longitude,
      latitude,
      city,area,postCode,pType,subtype,uCode,created_at,route_description)
      SELECT id,Address,
              CONCAT(Address,', ', area),
              CONCAT(Address,', ', area),
      		    longitude,
              latitude,
              city,area,postCode,pType,subtype,uCode,created_at, route_description
       FROM places");
      $this->line("\n\nFixing the new address for you . . . ");
      DB::select("UPDATE places_3 SET new_address = REPLACE(new_address, ', ',',');");
      $this->line("\nfixing comma to comma space ");
      DB::select("UPDATE places_3 SET new_address = REPLACE(new_address, ',',', ');");
      $this->line("\nfixing : ");
      DB::select("UPDATE places_3 SET new_address = REPLACE(new_address, ':',' ');");
      $this->line("\nfixing double spaces n comma");
      DB::select("UPDATE places_3 SET new_address = REPLACE(new_address, ', ,',',');");

      DB::select("UPDATE places_3 SET new_address = REPLACE(new_address, '  ',' ');");
      DB::select("UPDATE places_3 SET new_address = REPLACE(new_address, ', ,',', ');");
      $this->line("\n. . . . . .");

      $this->line("\nDone!!");
      $this->line("\n\nFixing the new alternate address for you . . . ");

      $this->line("\nfixing comma space to comma ");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, ', ',',');");
      $this->line("\nfixing comma to comma space ");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, ',',', ');");
      $this->line("\nfixing : ");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, ':',' ');");

      $this->line("\nreplacing comma with space spaces");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, ',',' ');");
      $this->line("\n. . . . . .");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, ', ,',',');");
      $this->line("\nfixing double spaces");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, '  ',' ');");
      DB::select("UPDATE places_3 SET alternate_address = REPLACE(alternate_address, ', ,',', ');");
      $stopTimer = microtime(true);
      $totalTime = $stopTimer-$startTimer;
      $this->line("\nTime: "+$totalTime);
      $this->line("\nDone!!");



    }
}
