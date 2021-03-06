<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Indexer\TNTGeoIndexer;
use App\Place;
use Exception;

class IndexAddressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'index:places';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index the places table';

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
      $tnt = new TNTSearch;
      $tnt->loadConfig([
          'driver'    => 'mysql',
          'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
          'database'  => 'ethikana',
          'username'  => 'dbmasteruser',
          'password'  => 'Amitayef5.7barikoi1216',
          'storage'   => '/var/www/html/ethikana/storage/custom/'
      ]);
        $indexer = $tnt->createIndex('places.index');
        $indexer->query('SELECT id,new_address,alternate_address,uCode,area,postCode,subType,pType,city,tags from places_3;');
        $indexer->run();
    /*    $candyShopIndexer = new TNTGeoIndexer;
        $candyShopIndexer->loadConfig([
           'driver'    => 'mysql',
           'host'      => 'localhost',
           'database'  => 'ethikana',
           'username'  => 'root',
           'password'  => 'root',
           'storage'   => '/var/www/html/ethikana/storage/custom/'
         ]);
        $candyShopIndexer->createIndex('nearby.index');
        $candyShopIndexer->query('SELECT id, longitude, latitude FROM places;');
        $candyShopIndexer->run();
        */
    }
}
