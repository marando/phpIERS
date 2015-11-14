<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Marando\IERS;

/**
 * Provides and interpolates IERS bulletin data
 *
 * @property float $jd  Julian day count
 * @property float $mjd Modified Julian day count
 */
class IERS {
  //----------------------------------------------------------------------------
  // Constants
  //----------------------------------------------------------------------------

  /**
   * Interpolation dataset count on either side of n value
   */
  const INTERP_COUNT = 5;

  /**
   * Hourly interval to check for data updates from IERS servers
   */
  const UPDATE_INTVL_H = 12;

  /**
   * Data files needed by this class
   */
  const FILES = [
      // ΔΤ (TT-UT) predictions
      'deltat.data',
      // ΔΤ (TT-UT) predictions
      'deltat.preds',
      // Final values for x, y, and UT1-UTC (dut1)
      'finals.all',
      // Historic ΔΤ (TDT-UT1)
      'historic_deltat.data',
      // Leap second file (TAI-UTC)
      'tai-utc.dat',
      // Readme files
      'readme',
      'readme.finals',
  ];

  /**
   * A list of IERS servers and their home paths
   */
  const SERVERS = [
      ['domain' => 'maia.usno.navy.mil', 'path' => '/ser7/'],
      ['domain' => 'toshi.nofs.navy.mil', 'path' => '/ser7/'],
      ['domain' => 'cddis.gsfc.nasa.gov', 'path' => '/pub/products/iers'],
  ];

  //----------------------------------------------------------------------------
  // Constructors
  //----------------------------------------------------------------------------

  /**
   * Creates a new instance from a Julian day count
   * @param float $jd
   */
  public function __construct($jd) {
    $this->jd = $jd;

    // Check for updates
    $this->update();
  }

  // // // Static

  /**
   * Creates a new instance from a Julian day count
   * @param  float  $jd
   * @return static
   */
  public static function jd($jd) {
    return new static($jd);
  }

  /**
   * Creates a new instance from a Modified Julian day count
   * @param  float  $mjd
   * @return static
   */
  public static function mjd($mjd) {
    return new static(2400000.5 + $mjd);
  }

  /**
   * Creates a new instance using the current time
   * @return static
   */
  public static function now() {
    $jd = unixtojd(time()) + microtime(true) - time();
    return new static($jd);
  }

  //----------------------------------------------------------------------------
  // Properties
  //----------------------------------------------------------------------------

  /**
   * Julian day count of this instance
   * @var float
   */
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

  /**
   * Interpolates the value of UT1-UTC (dut1) in seconds
   * @return float|boolean Returns false on error
   */
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

  /**
   * Interpolates the x celestial pole offset in seconds of arc
   * @return float|boolean Returns false on error
   */
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

  /**
   * Interpolates the y celestial pole offset in seconds of arc
   * @return float|boolean Returns false on error
   */
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

  /**
   * Returns if all needed remote data files defined in FILES exist locally
   * @return boolean
   */
  protected function filesExist() {
    foreach (static::FILES as $file)
      if (!file_exists($this->storage($file)))
        return false;

    return true;
  }

  /**
   * Connects to the first available IERS server mirror and returns an FTP
   * connection resource
   * @return resource  FTP resource
   * @throws Exception Occurs if no connection can be made
   */
  protected function ftp() {
    $servers = static::SERVERS;  // Server list
    // Try each server till success
    $ftp;
    for ($i = 0; $i < count($servers); $i++) {
      $ftpServer = $servers[$i]['domain'];
      $ftp       = ftp_connect($ftpServer);

      if ($ftp)
        if (ftp_login($ftp, 'anonymous', null))
          if (ftp_chdir($ftp, $servers[$i]['path']))
            if (ftp_pasv($ftp, true))
              return $ftp;  // Connected, return resource





    }

    // Unable to connect to a server
    throw new \Exception('Unable to connect to server to download data');
  }

  /**
   * Returns the number of hours since last updating the local data
   * @return float
   */
  protected function hoursSinceUpdate() {
    $file = $this->storage('.updated');  // Last updated file
    // Return 0 if no file
    if (!file_exists($file))
      return 0;

    // Return hours since last update
    return (time() - file_get_contents($file)) / 3600;
  }

  /**
   * Interpolates the y-value at a given x-value within a dataset using the
   * Lagrange interpolation algorithm
   *
   * @param  float $x     x-value to interpolate
   * @param  array $table Dataset
   * @return float        interpolated value of y
   */
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

  /**
   * Logs activity, used for recording remote file updates
   * @param string $data
   */
  protected function log($data) {
    $data = date(DATE_RSS, time()) . "\t$data\n";
    file_put_contents($this->storage('.log'), $data, FILE_APPEND);
  }

  /**
   * Saves to disk the last remote file update timestamp using the current time
   */
  protected function setUpdatedNow() {
    file_put_contents($this->storage('.updated'), time());
  }

  /**
   * Returns a file from local storage, and creates the directory in the event
   * that it does not exist
   *
   * @param  string $file Filename
   * @return string       Full relative path to the file
   */
  protected function storage($file = null) {
    $storagePath = 'data';
    if (!file_exists($storagePath))
      mkdir($storagePath);

    return $file ? "$storagePath/$file" : "$storagePath";
  }

  /**
   * Checks if the local files need to be updated and updates them. This occurs
   * if a local file is missing, or if the update time interval has been
   * exceeded. While updating local file sizes are compared to remote file sizes
   * and the files are only updated if they differ in size
   *
   * @return bool
   */
  protected function update() {
    // Check if everything is ok locally
    if ($this->filesExist())
      if ($this->hoursSinceUpdate() < static::UPDATE_INTVL_H)
        return false;

    // Log FTP diff procedure
    $this->log('DIFF');
    $ftp = $this->ftp();
    foreach (static::FILES as $file) {
      // Local file path
      $lFile = $this->storage($file);

      // Remote and local file sizes
      $rSize = ftp_size($ftp, $file);
      $lSize = file_exists($lFile) ? filesize($lFile) : 0;

      // Check if file sizes differ
      if ($lSize != $rSize) {
        // Download files if the sizes differ
        ftp_get($ftp, $lFile, $file, FTP_ASCII);
        $this->log($file);
      }
    }

    // Flag current last update time to now
    $this->setUpdatedNow();
  }

}
