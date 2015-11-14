<?php

/*
 * Copyright (C) 2015 ashley
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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
   * Interpolates the celestial pole offset value of x in seconds of arc
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
   * Interpolates the celestial pole offset value of y in seconds of arc
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

  /**
   * Interpolates the value of delta T (ΔΤ)
   * @return float|boolean Returns false on error
   */
  public function deltaT() {
    if ($this->jd < 2441714.5)
      return $this->deltaT_historic();

    if ($this->jd > $this->lastDeltaTjd())
      return $this->deltaT_predict();

    return $this->deltaT_base();
  }

  public function leapSec() {

  }

  // // // Protected

  protected function lastDeltaTjd() {
    $mLine = count(file($this->storage('deltat.data'))) - 1;
    $file  = new \SplFileObject($this->storage('deltat.data'));
    $file->seek($mLine);

    $line = $file->getCurrentLine();
    while (trim($line) == '') {
      $file->seek($mLine);
      $line = $file->getCurrentLine();
      $mLine--;
    }

    $y = (int)substr($line, 1, 4);
    $m = (int)substr($line, 6, 2);
    $d = (int)substr($line, 9, 2);
    static::iauCal2jd($y, $m, $d, $djm0, $djm);

    return $djm0 + $djm;
  }

  protected function deltaT_predict() {
    static::iauJd2cal(2400000.5, $this->mjd, $y, $m, $d, $fd);

    $file = new \SplFileObject($this->storage('deltat.preds'));

    $p = ($y - 2015) * 4 + 2;
    if ($p < 0)
      return false;

    if ($p == 0)
      $p = static::INTERP_COUNT;

    $maxLine = count(file($file->getRealPath())) - 1;
    if ($p + static::INTERP_COUNT > $maxLine)
      $p       = $maxLine - static::INTERP_COUNT;

    $file->seek($p);

    $ds = [];
    for ($i = $p - static::INTERP_COUNT; $i < $p + static::INTERP_COUNT; $i++) {
      $file->seek($i);
      $line = $file->getCurrentLine();

      $y  = (float)substr($line, 1, 7);
      $ΔT = (float)substr($line, 14, 6);

      $m;
      if ($y - intval($y) == 0)
        $m = 1;
      else if ($y - intval($y) <= 0.25)
        $m = 3;
      else if ($y - intval($y) <= 0.5)
        $m = 6;
      else if ($y - intval($y) <= 0.75)
        $m = 9;

      static::iauCal2jd(intval($y), $m, 1, $djm0, $djm);

      $ds[$i - $p + static::INTERP_COUNT]['x'] = $djm0 + $djm;
      $ds[$i - $p + static::INTERP_COUNT]['y'] = $ΔT;
    }
    
    return $this->lagrangeInterp($this->jd, $ds);
  }

  protected function deltaT_historic() {
    static::iauJd2cal(2400000.5, $this->mjd, $y, $m, $d, $fd);

    $file = new \SplFileObject($this->storage('historic_deltat.data'));

    $p = ($y - 1657) * 2 + 2;
    if ($p < 0)
      return false;

    if ($p == 0)
      $p = static::INTERP_COUNT;

    $maxLine = count(file($file->getRealPath())) - 1;
    if ($p + static::INTERP_COUNT > $maxLine)
      $p       = $maxLine - static::INTERP_COUNT;

    $file->seek($p);

    $ds = [];
    for ($i = $p - static::INTERP_COUNT; $i < $p + static::INTERP_COUNT; $i++) {
      $file->seek($i);
      $line = $file->getCurrentLine();

      $y  = (float)substr($line, 0, 8);
      $ΔT = (float)substr($line, 13, 6);

      $m  = intval($y) == $y ? 1 : 6;
      $jd = static::iauCal2jd((int)$y, $m, 1, $djm0, $djm);

      $ds[$i - $p + static::INTERP_COUNT]['x'] = $djm0 + $djm;
      $ds[$i - $p + static::INTERP_COUNT]['y'] = $ΔT;
    }

    return $this->lagrangeInterp($this->jd, $ds);
  }

  protected function deltaT_base() {
    static::iauJd2cal(2400000.5, $this->mjd, $iy, $im, $id, $fd);

    $p = ($iy - 1973) * 12 + $im - 2;
    if ($p < 0)
      return false;

    $file = new \SplFileObject($this->storage('deltat.data'));
    $file->seek($p);

    $maxLine = count(file($file->getRealPath())) - 1;

    if ($p == 0)
      $p = static::INTERP_COUNT;

    if ($p + static::INTERP_COUNT > $maxLine)
      $p = $maxLine - static::INTERP_COUNT;

    $ds = [];
    for ($i = $p - static::INTERP_COUNT; $i < $p + static::INTERP_COUNT; $i++) {
      $file->seek($i);
      $line = $file->getCurrentLine();

      $y = (int)substr($line, 1, 4);
      $m = (int)substr($line, 6, 2);
      $d = (int)substr($line, 9, 2);
      static::iauCal2jd($y, $m, $d, $djm0, $djm);

      $dT = (float)substr($line, 13, 7);

      $ds[$i - $p + static::INTERP_COUNT]['x'] = $djm0 + $djm;
      $ds[$i - $p + static::INTERP_COUNT]['y'] = $dT;
    }

    return $dT = $this->lagrangeInterp($this->jd, $ds);
  }

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

  // // // Private

  /**
   *
   * @param type $dj1
   * @param type $dj2
   * @param type $iy
   * @param type $im
   * @param type $id
   * @param type $fd
   * @return int
   */
  private static function iauJd2cal($dj1, $dj2, &$iy, &$im, &$id, &$fd) {
    /* Minimum and maximum allowed JD */
    $DJMIN = -68569.5;
    $DJMAX = 1e9;
    $jd;
    $l;
    $n;
    $i;
    $k;
    $dj;
    $d1;
    $d2;
    $f1;
    $f2;
    $f;
    $d;
    /* Verify date is acceptable. */
    $dj    = $dj1 + $dj2;
    if ($dj < $DJMIN || $dj > $DJMAX)
      return -1;
    /* Copy the date, big then small, and re-align to midnight. */
    if ($dj1 >= $dj2) {
      $d1 = $dj1;
      $d2 = $dj2;
    }
    else {
      $d1 = $dj2;
      $d2 = $dj1;
    }
    $d2 -= 0.5;
    /* Separate day and fraction. */
    $f1 = fmod($d1, 1.0);
    $f2 = fmod($d2, 1.0);
    $f  = fmod($f1 + $f2, 1.0);
    if ($f < 0.0)
      $f += 1.0;
    $d  = floor($d1 - $f1) + floor($d2 - $f2) + floor($f1 + $f2 - $f);
    $jd = floor($d) + 1;
    /* Express day in Gregorian calendar. */
    // Integer division parts of this block was modified in the PHP translation
    $l  = $jd + 68569;
    $n  = intval((4 * $l) / 146097);
    $l -= intval((146097 * $n + 3) / 4);
    $i  = intval((4000 * ($l + 1)) / 1461001);
    $l -= ((1461 * $i) / 4) - 31;
    $k  = intval((80 * $l) / 2447);
    $id = round($l - (2447 * $k) / 80);
    $l  = intval($k / 11);
    $im = (int)($k + 2 - 12 * $l);
    $iy = (int)(100 * ($n - 49) + $i + $l);
    $fd = $f;
    return 0;
  }

  private static function iauCal2jd($iy, $im, $id, &$djm0, &$djm) {
    $j;
    $ly;
    $my;
    $iypmy;
    /* Earliest year allowed (4800BC) */
    $IYMIN = -4799;
    /* Month lengths in days */
    $mtab  = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    /* Preset status. */
    $j     = 0;
    /* Validate year and month. */
    if ($iy < $IYMIN)
      return -1;
    if ($im < 1 || $im > 12)
      return -2;
    /* If February in a leap year, 1, otherwise 0. */
    $ly    = (($im == 2) && !($iy % 4) && ($iy % 100 || !($iy % 400)));
    /* Validate day, taking into account leap years. */
    if (($id < 1) || ($id > ($mtab[$im - 1] + $ly)))
      $j     = -3;
    /* Return result. */
    $my    = intval(($im - 14) / 12);
    $iypmy = intval($iy + $my);
    $djm0  = 2400000.5;
    $djm   = (double)( intval((1461 * ($iypmy + 4800)) / 4) +
            intval((367 * intval($im - 2 - 12 * $my)) / 12) -
            intval((3 * ( intval(($iypmy + 4900) / 100) )) / 4) +
            intval($id - 2432076));
    /* Return status. */
    return $j;
  }

}
