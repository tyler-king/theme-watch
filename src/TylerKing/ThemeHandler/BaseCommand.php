<?php
namespace TylerKing\ThemeHandler;

use Symfony\Component\Console\Command\Command;
use Touki\FTP\Connection\Connection;
use Touki\FTP\Connection\SSLConnection;
use Touki\FTP\FTPFactory;

class BaseCommand extends Command {
  protected $config,
            $connection
  ;
  
  public function setConfig($config) {
    $this->config = $config;
  }
  
  public function getConfig() {
    return $config;
  }
  
  protected function startConnection() {
    # Start a new connection with details given
    if ($this->config['ftp']['type'] == 'ssl') {
      $this->connection = new SSLConnection(
        $this->config['ftp']['host'],
        $this->config['ftp']['username'],
        $this->config['ftp']['password']
      );
    } else {
      $this->connection = new Connection(
        $this->config['ftp']['host'],
        $this->config['ftp']['username'],
        $this->config['ftp']['password']
      );
    }
    
    $this->connection->open();
    
    return $this->connection;
  }
  
  public function getConnection() {
    return $this->connection;
  }
  
  protected function startFTP() {
    $this->startConnection();

    # Build the FTP and move into the directory of the path provided
    $factory = new FTPFactory;
    $ftp = $factory->build($this->connection);
    $factory->getWrapper()->chdir($this->config['ftp']['path']);
    
    return $ftp;
  }
  
  protected function stopFTP() {
    $this->connection->close();
  }
  
  protected function getFileBase($file) {
    # Strips out the current working directory from the path (makes both paths local and remote to be relative to each other)
    $base = substr($file, strpos($file, getcwd()) + strlen(getcwd()) + 1);
    
    # Fix for Windows environments (convert \ to /)
    return str_replace('\\', '/', $base);
  }
  
  protected function isIgnoredFile($file) {
    if (sizeof($this->config['theme']['ignore']) === 0) {
      # Nothing to ignore
      return null;
    }
    
    foreach($this->config['theme']['ignore'] as $pattern) {
      if (fnmatch($pattern, $file)) {
        # Pattern matches, let the code know we need to ignore this file
        return true;
      }
    }
    
    # No matches found, do not ignore
    return false;
  }
}