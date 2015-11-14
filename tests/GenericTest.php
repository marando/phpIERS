<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class GenericTest extends PHPUnit_Framework_TestCase {

  /**
   * get data file
   * update daily
   * binary search for data poinr in file ?
   * find line
   * grab data swr around point
   * lagrange interp
   *
   */
  public function test() {



    $iers = Marando\IERS\IERS::mjd(57747);
    echo $iers->dut1();



return;
    exit;

    // Make data dir if not exists
    $dataDir = 'data';
    if (!file_exists($dataDir))
      mkdir($dataDir);

    // Define IERS data servers
    $servers = [
        'ftp://maia.usno.navy.mil/ser7/',
        'ftp://toshi.nofs.navy.mil/ser7/',
        'ftp://cddis.gsfc.nasa.gov/pub/products/iers'
    ];

    goto tempBypass;

    // Find first active server
    $server;
    foreach ($servers as $server)
      if (file_exists($server))
        break;

    // Define needed files
    $neededFiles = [
        'deltat.data',
        'deltat.preds',
        'finals.all',
        'readme',
        'readme.finals',
        'tai-utc.dat'
    ];

    // Download needed files
    foreach ($neededFiles as $file)
      exec("curl {$server}/{$file} > {$dataDir}/{$file}");

    tempBypass:



    // dut1
    $t    = microtime(true);
    $file = new SplFileObject("{$dataDir}/finals.all");

    $query = 2457039.500000 - 2400000.5;

    $file->seek(0);
    $mjd0 = (float)substr($file->getCurrentLine(), 7, 8);

    $i    = $query - $mjd0 - 1;
    $lnum = $file->seek($i);
    $line = $file->getCurrentLine();
    var_dump($line);



    echo "\n targeted search: " . round(microtime(true) - $t, 3) . " sec\n";
    exit;



    // Linear search
    $t    = microtime(true);
    $file = new SplFileObject("{$dataDir}/finals.all");
    for ($i = 0; $i < 15000; $i++) {
      $file->seek($i);
      $lnum = $file->getCurrentLine();

      $mjd = (float)substr($lnum, 7, 8);
      if ($mjd == 53611)
        break;
    }
    echo "\n Linear search: " . round(microtime(true) - $t, 3) . " sec\n";
    var_dump($i);









    ///
  }

}
