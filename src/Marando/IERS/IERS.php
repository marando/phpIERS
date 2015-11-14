<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Marando\IERS;

/**
 * @property float $jd  Julian day count
 * @property float $mjd Modified Julian day count
 */
class IERS {

  //----------------------------------------------------------------------------
  // Constants
  //----------------------------------------------------------------------------

  const INTERP_COUNT   = 12;
  const UPDATE_INTVL_H = 12;
  const FILES          = [
      'deltat.data',
      'deltat.preds',
      'finals.all',
      'readme',
      'readme.finals',
      'tai-utc.dat',
  ];

  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  public function __construct($jd) {
    $this->jd = $jd;

    $this->update();
  }

  // // // Static

  public static function jd($jd) {
    return new static($jd);
  }

  public static function mjd($mjd) {
    return new static(2400000.5 + $mjd);
  }

  public static function now() {
    $jd = unixtojd(time()) + microtime(true) - time();

    return new static($jd);
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  protected $jd;

  public function __get($name) {
    switch ($name) {
      case 'jd':
        return $this->{$name};

      case 'mjd':
        return $this->jd - 2400000.5;
    }
  }

  //----------------------------------------------------------------------------
  // Functions
  //----------------------------------------------------------------------------

  public function dut1() {
    // Load file
    $file = new \SplFileObject($this->storage('finals.all'));

    // Get instance MJD, and MJD at line 0
    $mjdQ = $this->mjd;
    $mjd0 = (int)substr($file->getCurrentLine(), 7, 8);

    // Check for requested MJD before first date
    if ($mjdQ < $mjd0)
      return false;

    // Determine nearest center pointer
    $p = $mjdQ - $mjd0 - 1;

    // Fix for values within |n| of lower bound
    if ($p < static::INTERP_COUNT && $p > -static::INTERP_COUNT)
      $p = static::INTERP_COUNT;

    // Compile dataset
    $ds = [];
    for ($i = $p - static::INTERP_COUNT; $i < $p + static::INTERP_COUNT; $i++) {
      $file->seek($i);
      $line = $file->getCurrentLine();

      // Parse data from line
      $mjd   = substr($line, 7, 8);
      $dut1f = substr($line, 156, 9);
      $dut1p = substr($line, 59, 9);

      // Use final value first, if not present use prediction
      $dut1 = trim($dut1f) ? $dut1f : $dut1p;

      // Check if no dut1 data, error
      if (trim($dut1) == '')
        return false;

      // Add the data
      $ds[$i - $p + static::INTERP_COUNT]['x'] = (float)$mjd;
      $ds[$i - $p + static::INTERP_COUNT]['y'] = (float)$dut1;
    }

    // Interp dut1
    return $this->lagrangeInterp($mjdQ, $ds);
  }

  public function x() {
    // Load file
    $file = new \SplFileObject($this->storage('finals.all'));

    // Get instance MJD, and MJD at line 0
    $mjdQ = $this->mjd;
    $mjd0 = (int)substr($file->getCurrentLine(), 7, 8);

    // Check for requested MJD before first date
    if ($mjdQ < $mjd0)
      return false;

    // Determine nearest center pointer
    $p = $mjdQ - $mjd0 - 1;

    // Fix for values within |n| of lower bound
    if ($p < static::INTERP_COUNT && $p > -static::INTERP_COUNT)
      $p = static::INTERP_COUNT;

    // Compile dataset
    $ds = [];
    for ($i = $p - static::INTERP_COUNT; $i < $p + static::INTERP_COUNT; $i++) {
      $file->seek($i);
      $line = $file->getCurrentLine();

      // Parse data from line
      $mjd = substr($line, 7, 8);
      $xf  = substr($line, 136, 9);
      $xp  = substr($line, 18, 9);

      // Use final value first, if not present use prediction
      $x = trim($xf) ? $xf : $xp;

      // Check if no dut1 data, error
      if (trim($x) == '')
        return false;

      // Add the data
      $ds[$i - $p + static::INTERP_COUNT]['x'] = (float)$mjd;
      $ds[$i - $p + static::INTERP_COUNT]['y'] = (float)$x;
    }

    // Interp pole x
    return $this->lagrangeInterp($mjdQ, $ds);
  }

  public function y() {
    // Load file
    $file = new \SplFileObject($this->storage('finals.all'));

    // Get instance MJD, and MJD at line 0
    $mjdQ = $this->mjd;
    $mjd0 = (int)substr($file->getCurrentLine(), 7, 8);

    // Check for requested MJD before first date
    if ($mjdQ < $mjd0)
      return false;

    // Determine nearest center pointer
    $p = $mjdQ - $mjd0 - 1;

    // Fix for values within |n| of lower bound
    if ($p < static::INTERP_COUNT && $p > -static::INTERP_COUNT)
      $p = static::INTERP_COUNT;

    // Compile dataset
    $ds = [];
    for ($i = $p - static::INTERP_COUNT; $i < $p + static::INTERP_COUNT; $i++) {
      $file->seek($i);
      $line = $file->getCurrentLine();

      // Parse data from line
      $mjd = substr($line, 7, 8);
      $yf  = substr($line, 146, 9);
      $yp  = substr($line, 27, 9);

      // Use final value first, if not present use prediction
      $y = trim($yf) ? $yf : $yp;

      // Check if no dut1 data, error
      if (trim($y) == '')
        return false;

      // Add the data
      $ds[$i - $p + static::INTERP_COUNT]['x'] = (float)$mjd;
      $ds[$i - $p + static::INTERP_COUNT]['y'] = (float)$y;
    }

    // Interp pole y
    return $this->lagrangeInterp($mjdQ, $ds);
  }

  // // // Protected

  protected function filesExist() {
    foreach (static::FILES as $file)
      if (!file_exists($this->storage($file)))
        return false;

    return true;
  }

  protected function ftp() {
    $servers = [
        ['domain' => 'maia.usno.navy.mil', 'path' => '/ser7/'],
        ['domain' => 'toshi.nofs.navy.mil', 'path' => '/ser7/'],
        ['domain' => 'cddis.gsfc.nasa.gov', 'path' => '/pub/products/iers'],
    ];

    $ftp;
    for ($i = 0; $i < count($servers); $i++) {
      $ftpServer = $servers[$i]['domain'];
      $ftp       = ftp_connect($ftpServer);

      if ($ftp)
        if (ftp_login($ftp, 'anonymous', null))
          if (ftp_chdir($ftp, $servers[$i]['path']))
            if (ftp_pasv($ftp, true))
              return $ftp;
    }

    throw new \Exception('Unable to connect');
  }

  protected function hoursSinceUpdate() {
    $file = $this->storage('.updated');

    if (!file_exists($file))
      return 0;

    return (time() - file_get_contents($file)) / 3600;
  }

  protected function lagrangeInterp($x, $table) {
    $sum = 0;
    for ($i = 0; $i < count($table); $i++) {
      $xi   = $table[$i]['x'];
      $prod = 1;

      for ($j = 0; $j < count($table); $j++) {
        if ($i != $j) {
          $xj = $table[$j]['x'];
          $prod *= ($x - $xj) / ($xi - $xj);
        }
      }

      $sum += $table[$i]['y'] * $prod;
    }

    return $sum;
  }

  protected function log($data) {
    $data = date(DATE_RSS, time()) . "\t$data\n";
    file_put_contents($this->storage('.log'), $data, FILE_APPEND);
  }

  protected function setUpdatedNow() {
    file_put_contents($this->storage('.updated'), time());
  }

  protected function storage($file = null) {
    $storagePath = 'data';
    if (!file_exists($storagePath))
      mkdir($storagePath);

    return $file ? "$storagePath/$file" : "$storagePath";
  }

  protected function update() {
    if ($this->filesExist())
      if ($this->hoursSinceUpdate() < static::UPDATE_INTVL_H)
        return;

    $this->log('DIFF');
    $ftp = $this->ftp();
    foreach (static::FILES as $file) {
      $lFile = $this->storage($file);

      $rSize = ftp_size($ftp, $file);
      $lSize = file_exists($lFile) ? filesize($lFile) : 0;

      if ($lSize != $rSize) {
        ftp_get($ftp, $lFile, $file, FTP_ASCII);
        $this->log($file);
      }
    }

    $this->setUpdatedNow();
  }

}
